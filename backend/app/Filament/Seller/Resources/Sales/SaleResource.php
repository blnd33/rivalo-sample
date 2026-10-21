<?php

namespace App\Filament\Seller\Resources\Sales;

use App\Enums\OrderStatus;
use App\Filament\Seller\Resources\Sales\Pages\ListSales;
use App\Models\OrderItem;
use App\Models\SellerLedgerEntry;
use App\Services\SellerLedgerService;
use App\Support\Money;
use App\Support\SellerContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The seller's order lines. Read-only, and without the customer: no name, phone or address.
 * A line counts on the statement once its order is completed (handed over and paid).
 */
class SaleResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $slug = 'sales';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('order_items.seller_id', SellerContext::id() ?? 0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('order:id,number,status,currency,placed_at,completed_at')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->select('order_items.*')
                ->orderByDesc('orders.placed_at'))
            ->columns([
                TextColumn::make('order.placed_at')->label(__('Ordered'))->dateTime('j M Y, H:i'),
                TextColumn::make('order.number')->label(__('Order'))->searchable(query: fn ($query, string $search) => $query->where('orders.number', 'like', '%'.addcslashes($search, '%_\\').'%')),
                TextColumn::make('product_name')->label(__('Product'))->weight('semibold')
                    ->description(fn (OrderItem $i) => $i->option_label.' · '.$i->sku),
                TextColumn::make('quantity')->label(__('Quantity'))
                    ->suffix(fn (OrderItem $i) => $i->returned_quantity ? ' · '.__(':count returned', ['count' => $i->returned_quantity]) : ''),
                TextColumn::make('line_total_minor')->label(__('Item value'))->state(fn (OrderItem $i) => Money::format($i->line_total_minor, $i->order->currency)),
                TextColumn::make('earnings')->label(__('Your share'))
                    ->state(function (OrderItem $i) {
                        $gross = $i->unit_price_minor * ($i->quantity - $i->returned_quantity);

                        return Money::format($gross - SellerLedgerService::commission($gross, (int) $i->commission_bps), $i->order->currency);
                    })
                    ->description(fn (OrderItem $i) => __('Commission :rate', ['rate' => rtrim(rtrim(number_format(((int) $i->commission_bps) / 100, 2, '.', ''), '0'), '.').' %'])),
                TextColumn::make('order.status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => match (true) {
                        $state === OrderStatus::Completed => __('Completed'),
                        $state === OrderStatus::Cancelled => __('Cancelled'),
                        default => __('In progress'),
                    })
                    ->color(fn (OrderStatus $state) => match (true) {
                        $state === OrderStatus::Completed => 'success',
                        $state === OrderStatus::Cancelled => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('counted')->label(__('On your statement'))
                    ->state(fn (OrderItem $i) => SellerLedgerEntry::where('idempotency_key', "sale:{$i->id}")->exists() ? __('Yes') : __('Not yet')),
            ])
            ->filters([
                SelectFilter::make('progress')->label(__('Status'))
                    ->options(['open' => __('In progress'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'open' => $query->whereNotIn('orders.status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value]),
                        'completed' => $query->where('orders.status', OrderStatus::Completed->value),
                        'cancelled' => $query->where('orders.status', OrderStatus::Cancelled->value),
                        default => $query,
                    }),
            ])
            ->emptyStateHeading(__('No sales yet'))
            ->emptyStateDescription(__('Orders for your products appear here.'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('Sale');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sales');
    }

    public static function getPages(): array
    {
        return ['index' => ListSales::route('/')];
    }
}
