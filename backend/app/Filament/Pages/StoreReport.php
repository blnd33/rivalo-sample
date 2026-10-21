<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Support\Audit;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Store sales from recorded orders: cash actually received and refunded, items sold per product,
 * and what is low on stock. Sample orders are counted separately.
 */
class StoreReport extends Page
{
    protected string $view = 'filament.pages.store-report';

    protected static ?string $slug = 'store-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Store;

    protected static ?int $navigationSort = 6;

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->until = $this->until ?: now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('store.manage');
    }

    public static function getNavigationLabel(): string
    {
        return __('Sales report');
    }

    public function getTitle(): string
    {
        return __('Sales report');
    }

    public function updated(): void
    {
        unset($this->totals, $this->products);
    }

    private function range(): array
    {
        $from = rescue(fn () => Carbon::parse($this->from)->startOfDay(), now()->startOfMonth(), false);
        $until = rescue(fn () => Carbon::parse($this->until)->endOfDay(), now()->endOfDay(), false);

        return [$from, $until];
    }

    /** Totals per currency for orders placed in the period. */
    #[Computed]
    public function totals(): array
    {
        [$from, $until] = $this->range();
        $totals = [];
        $orders = Order::whereBetween('placed_at', [$from, $until])->get(['currency', 'status', 'amount_paid_minor', 'amount_refunded_minor', 'total_minor', 'is_sample_data']);
        foreach ($orders as $o) {
            $t = &$totals[$o->currency.($o->is_sample_data ? ' (sample)' : '')];
            $t['currency'] = $o->currency;
            $t['sample'] = $o->is_sample_data;
            $t['orders'] = ($t['orders'] ?? 0) + 1;
            $t['completed'] = ($t['completed'] ?? 0) + ($o->status === OrderStatus::Completed ? 1 : 0);
            $t['cancelled'] = ($t['cancelled'] ?? 0) + ($o->status === OrderStatus::Cancelled ? 1 : 0);
            $t['open_value'] = ($t['open_value'] ?? 0) + ($o->status->isOpen() ? $o->total_minor : 0);
            $t['received'] = ($t['received'] ?? 0) + $o->amount_paid_minor;
            $t['refunded'] = ($t['refunded'] ?? 0) + $o->amount_refunded_minor;
            $t['net'] = ($t['net'] ?? 0) + $o->amount_paid_minor - $o->amount_refunded_minor;
            unset($t);
        }

        return $totals;
    }

    /** Items sold (in orders that were not cancelled), per product and size. */
    #[Computed]
    public function products(): array
    {
        [$from, $until] = $this->range();

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.placed_at', [$from, $until])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('order_items.product_name', 'order_items.option_label', 'order_items.sku', 'orders.currency')
            ->orderByDesc('units')
            ->selectRaw('order_items.product_name, order_items.option_label, order_items.sku, orders.currency, SUM(order_items.quantity) as units, SUM(order_items.returned_quantity) as returned, SUM(order_items.line_total_minor) as value')
            ->get()->map(fn ($r) => $r->toArray())->all();
    }

    #[Computed]
    public function lowStock(): array
    {
        return ProductVariant::query()->where('is_active', true)->where('stock', '<=', 3)
            ->with('product')->orderBy('stock')->limit(50)->get()
            ->filter(fn (ProductVariant $v) => $v->product->isOnSale())
            ->map(fn (ProductVariant $v) => ['name' => $v->product->name(), 'option' => $v->option_label, 'sku' => $v->sku, 'stock' => $v->stock])
            ->values()->all();
    }

    public function money(int $minor, string $currency): string
    {
        return Money::format($minor, $currency);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadCsv')
                ->label(__('Download CSV'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): StreamedResponse {
                    [$from, $until] = $this->range();
                    Audit::record('store.report_exported', null, new: ['from' => $from->toDateString(), 'until' => $until->toDateString()]);
                    $orders = Order::whereBetween('placed_at', [$from, $until])->with('user')->withSum('items', 'quantity')->orderBy('placed_at')->get();

                    return response()->streamDownload(function () use ($orders) {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['number', 'placed_at', 'customer', 'status', 'fulfillment', 'items', 'currency', 'subtotal_minor', 'delivery_fee_minor',
                            'total_minor', 'payment_status', 'paid_minor', 'refunded_minor', 'receipt', 'is_sample_data']);
                        foreach ($orders as $o) {
                            fputcsv($out, [
                                $o->number, $o->placed_at?->toIso8601String(), $o->user?->username, $o->status->value, $o->fulfillment->value,
                                $o->items_sum_quantity, $o->currency, $o->subtotal_minor, $o->delivery_fee_minor, $o->total_minor,
                                $o->payment_status->value, $o->amount_paid_minor, $o->amount_refunded_minor, $o->payment_reference, $o->is_sample_data ? 'yes' : 'no',
                            ]);
                        }
                        fclose($out);
                    }, 'rivalo-store-'.$from->format('Y-m-d').'-'.$until->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
