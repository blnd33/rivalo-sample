<?php

namespace App\Filament\Resources\Orders;

use App\Enums\AdminNavigationGroup;
use App\Enums\Fulfillment;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Seller;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** Store orders. Changed only through the actions on each order (OrderService). */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Store;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $new = Order::where('status', OrderStatus::Placed->value)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('New orders to confirm');
    }

    public static function statusColor(OrderStatus $s): string
    {
        return match ($s) {
            OrderStatus::Placed => 'warning',
            OrderStatus::Confirmed, OrderStatus::Ready, OrderStatus::OutForDelivery => 'info',
            OrderStatus::Completed => 'success',
            OrderStatus::Cancelled => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (string $field) => fn (Order $o) => Money::format((int) $o->{$field}, $o->currency);

        return $schema->columns(3)->components([
            Section::make(__('Items'))->columnSpan(2)->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('product_name')->label(__('Product'))->weight('semibold')
                        // Seller lines (Milestone 9): staff need to know whose stock to pick.
                        ->state(fn ($record) => $record->seller_id
                            ? $record->product_name.' · '.__('Sold by :seller', ['seller' => Seller::whereKey($record->seller_id)->value('name')])
                            : $record->product_name),
                    TextEntry::make('option_label')->label(__('Size or option'))->state(fn ($record) => $record->option_label.' · '.$record->sku),
                    TextEntry::make('quantity')->label(__('Quantity'))
                        ->suffix(fn ($record) => $record->returned_quantity ? ' · '.__(':count returned', ['count' => $record->returned_quantity]) : ''),
                    TextEntry::make('line_total_minor')->label(__('Total'))->state(fn ($record) => Money::format($record->line_total_minor, $record->order->currency)),
                ]),
                TextEntry::make('subtotal_minor')->label(__('Subtotal'))->state($money('subtotal_minor'))->inlineLabel(),
                TextEntry::make('delivery_fee_minor')->label(__('Delivery fee'))->state($money('delivery_fee_minor'))->inlineLabel(),
                TextEntry::make('total_minor')->label(__('Total to collect'))->state($money('total_minor'))->inlineLabel()->weight('bold'),
                TextEntry::make('customer_note')->label(__('Customer note'))->placeholder('—'),
            ]),
            Section::make(__('Order'))->columnSpan(1)->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => self::statusColor($state)),
                TextEntry::make('user.username')->label(__('Customer'))->prefix('@'),
                TextEntry::make('placed_at')->dateTime('d M Y H:i')->label(__('Placed')),
                TextEntry::make('payment_status')->label(__('Payment'))->badge()
                    ->formatStateUsing(fn (OrderPaymentStatus $state) => $state->label())
                    ->color(fn (OrderPaymentStatus $state) => $state === OrderPaymentStatus::Paid ? 'success' : ($state === OrderPaymentStatus::Unpaid ? 'warning' : 'gray')),
                TextEntry::make('payment_reference')->label(__('Receipt'))->placeholder('—'),
                TextEntry::make('amount_refunded_minor')->label(__('Refunded'))->state($money('amount_refunded_minor'))
                    ->visible(fn (Order $o) => $o->amount_refunded_minor > 0),
                TextEntry::make('cancel_reason')->label(__('Cancellation'))->visible(fn (Order $o) => $o->status === OrderStatus::Cancelled)
                    ->prefix(fn (Order $o) => $o->cancelled_by === 'customer' ? __('By the customer').': ' : __('By staff').': ')->placeholder('—'),
            ]),
            Section::make(fn (Order $o) => $o->fulfillment === Fulfillment::Pickup ? __('Pickup') : __('Delivery'))->columnSpanFull()->columns(3)->schema([
                TextEntry::make('pickupLocation.name')->label(__('Pickup point'))->visible(fn (Order $o) => $o->fulfillment === Fulfillment::Pickup),
                TextEntry::make('deliveryZone.name')->label(__('Delivery area'))->visible(fn (Order $o) => $o->fulfillment === Fulfillment::Delivery),
                TextEntry::make('recipient_name')->label(__('Recipient'))->visible(fn (Order $o) => $o->fulfillment === Fulfillment::Delivery),
                TextEntry::make('recipient_phone_visible')->label(__('Phone'))->state(fn (Order $o) => $o->recipient_phone)
                    ->visible(fn (Order $o) => $o->fulfillment === Fulfillment::Delivery),
                TextEntry::make('delivery_address_visible')->label(__('Address'))->state(fn (Order $o) => $o->delivery_address)
                    ->visible(fn (Order $o) => $o->fulfillment === Fulfillment::Delivery)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('placed_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user')->withSum('items', 'quantity'))
            ->columns([
                TextColumn::make('number')->searchable()->weight('semibold')
                    ->description(fn (Order $o) => $o->is_sample_data ? new HtmlString('<span class="rivalo-sample-badge">'.e(__('Sample data')).'</span>') : null),
                TextColumn::make('user.username')->label(__('Customer'))->prefix('@')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => self::statusColor($state)),
                TextColumn::make('fulfillment')->formatStateUsing(fn (Fulfillment $state) => $state->label()),
                TextColumn::make('items_sum_quantity')->label(__('Items')),
                TextColumn::make('total_minor')->label(__('Total'))->formatStateUsing(fn (Order $o) => Money::format($o->total_minor, $o->currency)),
                TextColumn::make('payment_status')->label(__('Payment'))->badge()->formatStateUsing(fn (OrderPaymentStatus $state) => $state->label())
                    ->color(fn (OrderPaymentStatus $state) => $state === OrderPaymentStatus::Paid ? 'success' : ($state === OrderPaymentStatus::Unpaid ? 'warning' : 'gray')),
                TextColumn::make('placed_at')->since()->label(__('Placed'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::options()),
                SelectFilter::make('fulfillment')->options(Fulfillment::options()),
                SelectFilter::make('payment_status')->label(__('Payment'))->options(OrderPaymentStatus::options()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getModelLabel(): string
    {
        return __('Order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Orders');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
