<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Fulfillment;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Filament\Concerns\HandlesDomainRules;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    use HandlesDomainRules;

    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return __('Order :number', ['number' => $this->record->number]);
    }

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    private function done(string $title): void
    {
        Notification::make()->success()->title($title)->send();
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        /** @var Order $o */
        $o = $this->record;

        return [
            Action::make('confirm')->label(__('Confirm order'))->icon(Heroicon::OutlinedCheck)
                ->visible(fn () => $this->record->status === OrderStatus::Placed)
                ->modalDescription(__('Confirm that the items are set aside for this customer.'))
                ->requiresConfirmation()
                ->action(function () {
                    self::attempt(fn () => $this->orders()->confirm($this->record, auth()->user()));
                    $this->done(__('Order confirmed'));
                }),
            Action::make('ready')->label(__('Ready for pickup'))->icon(Heroicon::OutlinedArchiveBox)
                ->visible(fn () => $this->record->status === OrderStatus::Confirmed && $this->record->fulfillment === Fulfillment::Pickup)
                ->modalDescription(__('The customer gets a message that the order is ready to collect.'))
                ->requiresConfirmation()
                ->action(function () {
                    self::attempt(fn () => $this->orders()->markReady($this->record, auth()->user()));
                    $this->done(__('Customer notified'));
                }),
            Action::make('outForDelivery')->label(__('Out for delivery'))->icon(Heroicon::OutlinedTruck)
                ->visible(fn () => $this->record->status === OrderStatus::Confirmed && $this->record->fulfillment === Fulfillment::Delivery)
                ->modalDescription(__('The customer gets a message that the order is on its way.'))
                ->requiresConfirmation()
                ->action(function () {
                    self::attempt(fn () => $this->orders()->markOutForDelivery($this->record, auth()->user()));
                    $this->done(__('Customer notified'));
                }),
            Action::make('complete')->label(__('Handed over and paid'))->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn () => in_array($this->record->status, [OrderStatus::Ready, OrderStatus::OutForDelivery], true))
                ->modalDescription(fn () => $this->record->payment_status === OrderPaymentStatus::Unpaid
                    ? __('Record that :amount was received in cash and the customer has the items. A receipt number is created.', ['amount' => Money::format($this->record->total_minor, $this->record->currency)])
                    : __('Record that the customer has the items.'))
                ->requiresConfirmation()
                ->action(function () {
                    self::attempt(function () {
                        if ($this->record->payment_status === OrderPaymentStatus::Unpaid) {
                            $this->orders()->recordPayment($this->record, auth()->user());
                        }
                        $this->orders()->complete($this->record->fresh(), auth()->user());
                    });
                    $this->done(__('Order completed'));
                }),
            ActionGroup::make([
                Action::make('recordPayment')->label(__('Record cash payment only'))->icon(Heroicon::OutlinedBanknotes)
                    ->visible(fn () => $this->record->status->isOpen() && $this->record->payment_status === OrderPaymentStatus::Unpaid)
                    ->modalDescription(fn () => __('Record that :amount was received in cash. A receipt number is created.', ['amount' => Money::format($this->record->total_minor, $this->record->currency)]))
                    ->requiresConfirmation()
                    ->action(function () {
                        self::attempt(fn () => $this->orders()->recordPayment($this->record, auth()->user()));
                        $this->done(__('Payment recorded'));
                    }),
                Action::make('refund')->label(__('Record refund or return'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('warning')
                    ->visible(fn () => $this->record->amount_paid_minor > $this->record->amount_refunded_minor)
                    ->modalDescription(__('Record cash you actually handed back. Returned items in sellable condition go back into stock.'))
                    ->schema(fn () => [
                        TextInput::make('amount_minor')->label(__('Cash refunded (minor units)'))->integer()->minValue(0)
                            ->maxValue($this->record->amount_paid_minor - $this->record->amount_refunded_minor)->required()->suffix($this->record->currency),
                        ...$this->record->items->map(fn ($item) => TextInput::make("returns.{$item->id}")
                            ->label(__('Returned to stock: :item', ['item' => $item->product_name.' · '.$item->option_label]))
                            ->integer()->minValue(0)->maxValue($item->quantity - $item->returned_quantity)->default(0))->all(),
                        Textarea::make('reason')->label(__('Reason (audited)'))->required(),
                    ])
                    ->action(function (array $data) {
                        self::attempt(fn () => $this->orders()->refund($this->record, auth()->user(), (int) $data['amount_minor'], $data['reason'], array_map('intval', $data['returns'] ?? [])));
                        $this->done(__('Refund recorded'));
                    }),
                Action::make('cancel')->label(__('Cancel order'))->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn () => $this->record->status->isOpen())
                    ->modalDescription(__('The items go back into stock and the customer is told the reason.'))
                    ->schema([Textarea::make('reason')->label(__('Reason (shown to the customer)'))->required()->maxLength(500)])
                    ->action(function (array $data) {
                        self::attempt(fn () => $this->orders()->cancel($this->record, auth()->user(), $data['reason']));
                        $this->done(__('Order cancelled'));
                    }),
            ])->label(__('More'))->button()->color('gray'),
        ];
    }
}
