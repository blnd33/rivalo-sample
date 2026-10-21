<?php

namespace App\Services;

use App\Enums\Fulfillment;
use App\Enums\InventoryReason;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Store staff handling of orders: confirm, ready for pickup / out for delivery, record the cash
 * payment, complete, cancel, and record refunds and returns. Money is recorded as it really
 * moved (cash at handover, cash refunds); nothing here transfers money.
 */
class OrderService
{
    public function __construct(
        private readonly StoreService $store,
        private readonly InventoryService $inventory,
        private readonly Notifier $notifier,
        private readonly SellerLedgerService $sellerLedger,
    ) {}

    private function assertStaff(User $staff): void
    {
        if (! $staff->can('store.manage')) {
            throw DomainRuleException::make('forbidden', 'Only store staff can change orders.', 403);
        }
    }

    /** @param  callable(Order): void  $change */
    private function transition(Order $order, User $staff, array $from, OrderStatus $to, string $timestamp, ?callable $change = null): Order
    {
        $this->assertStaff($staff);

        return DB::transaction(function () use ($order, $staff, $from, $to, $timestamp, $change) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, $from, true)) {
                throw DomainRuleException::make('invalid_order_transition', 'This order is :status and cannot be moved to :next.', 409, ['status' => $order->status->label(), 'next' => $to->label()]);
            }
            $old = $order->status;
            if ($change) {
                $change($order);
            }
            $order->update(['status' => $to, $timestamp => now()]);
            Audit::record('order.status_changed', $order, ['status' => $old->value], ['status' => $to->value], actorId: $staff->id);

            return $order;
        });
    }

    public function confirm(Order $order, User $staff): Order
    {
        return $this->transition($order, $staff, [OrderStatus::Placed], OrderStatus::Confirmed, 'confirmed_at');
    }

    public function markReady(Order $order, User $staff): Order
    {
        $order = $this->transition($order, $staff, [OrderStatus::Confirmed], OrderStatus::Ready, 'ready_at', function (Order $o) {
            if ($o->fulfillment !== Fulfillment::Pickup) {
                throw DomainRuleException::make('wrong_fulfillment', 'This order is for delivery. Mark it out for delivery instead.');
            }
        });
        $this->notifier->send([$order->user_id], 'order_ready', ['number' => $order->number, 'place' => $order->pickupLocation?->name], ['order', $order->number], "order_ready:{$order->id}");

        return $order;
    }

    public function markOutForDelivery(Order $order, User $staff): Order
    {
        $order = $this->transition($order, $staff, [OrderStatus::Confirmed], OrderStatus::OutForDelivery, 'ready_at', function (Order $o) {
            if ($o->fulfillment !== Fulfillment::Delivery) {
                throw DomainRuleException::make('wrong_fulfillment', 'This order is for pickup. Mark it ready for pickup instead.');
            }
        });
        $this->notifier->send([$order->user_id], 'order_out_for_delivery', ['number' => $order->number], ['order', $order->number], "order_out:{$order->id}");

        return $order;
    }

    /** Cash received at handover. One payment of the full total, with a receipt number. */
    public function recordPayment(Order $order, User $staff): Order
    {
        $this->assertStaff($staff);

        return DB::transaction(function () use ($order, $staff) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::Cancelled) {
                throw DomainRuleException::make('order_cancelled', 'This order was cancelled.', 409);
            }
            if ($order->payment_status !== OrderPaymentStatus::Unpaid) {
                throw DomainRuleException::make('already_paid', 'The payment for this order is already recorded.', 409);
            }
            $order->update([
                'payment_status' => OrderPaymentStatus::Paid,
                'amount_paid_minor' => $order->total_minor,
                'payment_reference' => sprintf('SR-%06d', $order->id),
                'paid_at' => now(),
                'paid_recorded_by_id' => $staff->id,
            ]);
            Audit::record('order.payment_recorded', $order, new: ['amount_minor' => $order->total_minor, 'currency' => $order->currency, 'reference' => $order->payment_reference], actorId: $staff->id);

            return $order;
        });
    }

    /**
     * Handed over to the customer. Requires the payment to be recorded. Seller lines are added to
     * their sellers' statements in the same transaction (Milestone 9).
     */
    public function complete(Order $order, User $staff): Order
    {
        $order = $this->transition($order, $staff, [OrderStatus::Ready, OrderStatus::OutForDelivery], OrderStatus::Completed, 'completed_at', function (Order $o) use ($staff) {
            if ($o->payment_status === OrderPaymentStatus::Unpaid) {
                throw DomainRuleException::make('payment_required', 'Record the cash payment before completing the order.');
            }
            $this->sellerLedger->postSales($o, $staff->id);
        });
        $this->notifier->send([$order->user_id], 'order_completed', ['number' => $order->number], ['order', $order->number], "order_completed:{$order->id}");

        return $order;
    }

    public function cancel(Order $order, User $staff, string $reason): Order
    {
        $this->assertStaff($staff);
        if (mb_strlen(trim($reason)) < 3) {
            throw DomainRuleException::make('reason_required', 'A reason is required.');
        }

        $order = DB::transaction(function () use ($order, $staff, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->status->isOpen()) {
                throw DomainRuleException::make('order_closed', 'This order is already :status.', 409, ['status' => $order->status->label()]);
            }
            if ($order->amount_paid_minor - $order->amount_refunded_minor > 0) {
                throw DomainRuleException::make('refund_first', 'Money was received for this order. Record the refund first.', 409);
            }
            $this->store->releaseStock($order, $staff->id, 'Cancelled by staff: '.$reason);
            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => 'staff', 'cancel_reason' => mb_substr(trim($reason), 0, 500)]);
            Audit::record('order.cancelled', $order, new: ['by' => 'staff'], reason: $reason, actorId: $staff->id);

            return $order;
        });
        $this->notifier->send([$order->user_id], 'order_cancelled', ['number' => $order->number, 'reason' => $order->cancel_reason], ['order', $order->number], "order_cancelled:{$order->id}");

        return $order;
    }

    /**
     * Records cash actually handed back to the customer, and optionally items returned to stock.
     *
     * @param  array<int, int>  $returns  order_item_id => quantity returned in resellable condition
     */
    public function refund(Order $order, User $staff, int $amountMinor, string $reason, array $returns = []): Order
    {
        $this->assertStaff($staff);
        if (mb_strlen(trim($reason)) < 3) {
            throw DomainRuleException::make('reason_required', 'A reason is required.');
        }

        return DB::transaction(function () use ($order, $staff, $amountMinor, $reason, $returns) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $refundable = $order->amount_paid_minor - $order->amount_refunded_minor;
            if ($amountMinor < 0 || $amountMinor > $refundable || ($amountMinor === 0 && array_sum($returns) === 0)) {
                throw DomainRuleException::make('invalid_refund', 'The refund must be between 0 and :max.', 422, ['max' => Money::format($refundable, $order->currency)]);
            }

            foreach ($returns as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $item = OrderItem::where('order_id', $order->id)->whereKey($itemId)->firstOrFail();
                if ($item->returned_quantity + $qty > $item->quantity) {
                    throw DomainRuleException::make('invalid_return', 'More items returned than were bought.');
                }
                $item->update(['returned_quantity' => $item->returned_quantity + $qty]);
                // A seller's sale already counted on their statement is reversed for the returned items.
                $this->sellerLedger->postReturn($item, $qty, $item->returned_quantity, $staff->id, $reason);
                if ($item->product_variant_id) {
                    $variant = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();
                    if ($variant) {
                        $this->inventory->move($variant, $qty, InventoryReason::Return, $order->id, $reason, $staff->id);
                    }
                }
            }

            $refunded = $order->amount_refunded_minor + $amountMinor;
            $order->update([
                'amount_refunded_minor' => $refunded,
                'payment_status' => $refunded === 0 ? $order->payment_status
                    : ($refunded >= $order->amount_paid_minor ? OrderPaymentStatus::Refunded : OrderPaymentStatus::PartiallyRefunded),
            ]);
            Audit::record('order.refund_recorded', $order, new: ['amount_minor' => $amountMinor, 'currency' => $order->currency, 'returns' => $returns], reason: $reason, actorId: $staff->id);

            return $order;
        });
    }
}
