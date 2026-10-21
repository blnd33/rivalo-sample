<?php

namespace App\Services;

use App\Enums\Fulfillment;
use App\Enums\InventoryReason;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Models\CartItem;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Audit;
use App\Support\FeatureFlags;
use Illuminate\Support\Facades\DB;

/**
 * The customer side of the store (Milestone 8): bag, checkout and cancelling your own order.
 *
 * - Payment is cash at handover (pickup or delivery). No card payments until a provider is verified.
 * - Stock is reserved when the order is placed (so nothing is oversold) and returned if the order
 *   is cancelled. Prices and names are copied into the order, so catalog edits never change it.
 * - Delivery exists only for areas staff configured, each with its own fee.
 */
class StoreService
{
    public const MAX_BAG_LINES = 20;

    public const MAX_OPEN_ORDERS = 3;

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly Notifier $notifier,
    ) {}

    public static function currency(): string
    {
        return strtoupper((string) config('rivalo.store.currency', 'IQD'));
    }

    public function assertOpen(): void
    {
        if (! FeatureFlags::enabled(FeatureFlags::STORE)) {
            throw DomainRuleException::make('store_closed', 'The store is not open yet.', 404);
        }
    }

    // ---------------------------------------------------------------- Bag

    public function setQuantity(User $user, ProductVariant $variant, int $quantity): void
    {
        $this->assertOpen();
        if ($quantity <= 0) {
            CartItem::where('user_id', $user->id)->where('product_variant_id', $variant->id)->delete();

            return;
        }

        $product = $variant->product;
        if (! $variant->is_active || ! $product->isOnSale()) {
            throw DomainRuleException::make('product_unavailable', 'This item is not available.');
        }
        if ($quantity > $product->max_per_order) {
            throw DomainRuleException::make('quantity_limit', 'You can buy at most :count of this item per order.', 422, ['count' => $product->max_per_order]);
        }
        if ($quantity > $variant->stock) {
            throw DomainRuleException::make('insufficient_stock', 'Only :count left.', 409, ['count' => $variant->stock]);
        }
        $exists = CartItem::where('user_id', $user->id)->where('product_variant_id', $variant->id)->exists();
        if (! $exists && CartItem::where('user_id', $user->id)->count() >= self::MAX_BAG_LINES) {
            throw DomainRuleException::make('bag_full', 'Your bag is full. Check out or remove something first.');
        }

        CartItem::updateOrCreate(['user_id' => $user->id, 'product_variant_id' => $variant->id], ['quantity' => $quantity]);
    }

    /**
     * The bag with today's prices and availability.
     *
     * @return array{lines: list<array<string, mixed>>, subtotal_minor: int, currency: string, ready: bool}
     */
    public function bag(User $user): array
    {
        $items = CartItem::where('user_id', $user->id)->with(['variant.product.images', 'variant.product.seller'])->orderBy('id')->get();
        $lines = [];
        $subtotal = 0;
        $ready = $items->isNotEmpty();

        foreach ($items as $item) {
            $variant = $item->variant;
            $product = $variant->product;
            $unit = $variant->unitPrice($product);
            $status = match (true) {
                ! $variant->is_active || ! $product->isOnSale() || $variant->stock === 0 => 'unavailable',
                $item->quantity > $variant->stock => 'reduced_stock',
                default => 'ok',
            };
            if ($status !== 'ok') {
                $ready = false;
            } else {
                $subtotal += $unit * $item->quantity;
            }
            $lines[] = [
                'variant_id' => $variant->id,
                'product_slug' => $product->slug,
                'product_name' => $product->name(),
                'option_label' => $variant->option_label,
                'image_url' => $product->images->first()?->url(),
                'category' => $product->category->value,
                'seller' => $product->seller ? ['slug' => $product->seller->slug, 'name' => $product->seller->name] : null,
                'unit_price_minor' => $unit,
                'quantity' => $item->quantity,
                'line_total_minor' => $unit * $item->quantity,
                'status' => $status,
                'available' => max(0, min($variant->stock, $product->max_per_order)),
            ];
        }

        return ['lines' => $lines, 'subtotal_minor' => $subtotal, 'currency' => self::currency(), 'ready' => $ready];
    }

    // ---------------------------------------------------------------- Checkout

    /**
     * @param  array{fulfillment: string, pickup_location_id?: ?int, delivery_zone_id?: ?int, recipient_name?: ?string,
     *     recipient_phone?: ?string, delivery_address?: ?string, note?: ?string, expected_total_minor?: ?int}  $data
     */
    public function checkout(User $user, array $data): Order
    {
        $this->assertOpen();
        if (! $user->isActive()) {
            throw DomainRuleException::make('account_unavailable', 'Your account cannot place orders.', 403);
        }
        if (! $user->hasVerifiedEmail()) {
            throw DomainRuleException::make('email_unverified', 'Verify your email address before ordering.', 403);
        }
        $fulfillment = Fulfillment::from($data['fulfillment']);

        return DB::transaction(function () use ($user, $data, $fulfillment) {
            // Serialize checkouts of the same customer (open-order limit, bag consumption).
            User::whereKey($user->id)->lockForUpdate()->first();

            $open = Order::where('user_id', $user->id)->whereIn('status', [OrderStatus::Placed->value, OrderStatus::Confirmed->value, OrderStatus::Ready->value, OrderStatus::OutForDelivery->value])->count();
            if ($open >= self::MAX_OPEN_ORDERS) {
                throw DomainRuleException::make('too_many_open_orders', 'You have :count orders in progress. Collect or cancel one before ordering again.', 409, ['count' => $open]);
            }

            $items = CartItem::where('user_id', $user->id)->orderBy('product_variant_id')->get();
            if ($items->isEmpty()) {
                throw DomainRuleException::make('bag_empty', 'Your bag is empty.');
            }

            // Lock every variant in id order (avoids deadlocks between concurrent checkouts).
            $variants = ProductVariant::whereIn('id', $items->pluck('product_variant_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $products = Product::whereIn('id', $variants->pluck('product_id'))->with('seller')->get()->keyBy('id');

            $lines = [];
            $subtotal = 0;
            foreach ($items as $item) {
                $variant = $variants[$item->product_variant_id];
                $product = $products[$variant->product_id];
                if (! $variant->is_active || ! $product->isOnSale()) {
                    throw DomainRuleException::make('product_unavailable', ':item is no longer available. Remove it from your bag.', 409, ['item' => $product->name().' · '.$variant->option_label]);
                }
                if ($item->quantity > $variant->stock) {
                    throw DomainRuleException::make('insufficient_stock', 'Only :count of :item left. Update your bag.', 409, ['count' => $variant->stock, 'item' => $product->name().' · '.$variant->option_label]);
                }
                if ($item->quantity > $product->max_per_order) {
                    throw DomainRuleException::make('quantity_limit', 'You can buy at most :count of this item per order.', 422, ['count' => $product->max_per_order]);
                }
                if ($product->currency !== self::currency()) {
                    throw DomainRuleException::make('product_unavailable', ':item is no longer available. Remove it from your bag.', 409, ['item' => $product->name()]);
                }
                $unit = $variant->unitPrice($product);
                $subtotal += $unit * $item->quantity;
                $lines[] = [$variant, $product, $unit, $item->quantity];
            }

            [$pickupId, $zone, $recipient] = $this->fulfillmentDetails($fulfillment, $data);
            $fee = $zone?->fee_minor ?? 0;
            $total = $subtotal + $fee;

            // The customer confirmed a total on screen; never charge a different one silently.
            if (isset($data['expected_total_minor']) && (int) $data['expected_total_minor'] !== $total) {
                throw DomainRuleException::make('price_changed', 'Prices or fees changed since you opened checkout. Check the new total.', 409);
            }

            $order = Order::create([
                'number' => 'PENDING-'.bin2hex(random_bytes(4)),
                'user_id' => $user->id,
                'status' => OrderStatus::Placed,
                'fulfillment' => $fulfillment,
                'pickup_location_id' => $pickupId,
                'delivery_zone_id' => $zone?->id,
                'recipient_name' => $recipient['name'] ?? null,
                'recipient_phone' => $recipient['phone'] ?? null,
                'delivery_address' => $recipient['address'] ?? null,
                'customer_note' => filled($data['note'] ?? null) ? mb_substr(trim($data['note']), 0, 500) : null,
                'currency' => self::currency(),
                'subtotal_minor' => $subtotal,
                'delivery_fee_minor' => $fee,
                'total_minor' => $total,
                'payment_method' => $fulfillment->paymentMethod(),
                'payment_status' => OrderPaymentStatus::Unpaid,
                'placed_at' => now(),
            ]);
            $order->update(['number' => sprintf('ORD-%06d', $order->id)]);

            foreach ($lines as [$variant, $product, $unit, $qty]) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name('en'),
                    'option_label' => $variant->option_label,
                    'sku' => $variant->sku,
                    'unit_price_minor' => $unit,
                    'quantity' => $qty,
                    'line_total_minor' => $unit * $qty,
                    // Seller lines keep the commission agreed at the time of the order (Milestone 9).
                    'seller_id' => $product->seller_id,
                    'commission_bps' => $product->seller?->commission_bps,
                ]);
                $this->inventory->move($variant, -$qty, InventoryReason::Order, $order->id, actorId: $user->id);
            }

            CartItem::where('user_id', $user->id)->delete();
            Audit::record('order.placed', $order, new: ['total_minor' => $total, 'currency' => $order->currency, 'fulfillment' => $fulfillment->value], actorId: $user->id);
            $this->notifier->send($user, 'order_placed', ['number' => $order->number], ['order', $order->number], "order_placed:{$order->id}");

            return $order;
        });
    }

    /** @return array{0: ?int, 1: ?DeliveryZone, 2: array<string, string>} */
    private function fulfillmentDetails(Fulfillment $fulfillment, array $data): array
    {
        if ($fulfillment === Fulfillment::Pickup) {
            $location = PickupLocation::where('is_active', true)->find($data['pickup_location_id'] ?? 0);
            if (! $location) {
                throw DomainRuleException::make('pickup_unavailable', 'Choose a pickup point.');
            }

            return [$location->id, null, []];
        }

        $zone = DeliveryZone::where('is_active', true)->find($data['delivery_zone_id'] ?? 0);
        if (! $zone) {
            throw DomainRuleException::make('delivery_unavailable', 'Delivery is not available to this area. Choose another area or pickup.');
        }
        $recipient = [
            'name' => trim((string) ($data['recipient_name'] ?? '')),
            'phone' => trim((string) ($data['recipient_phone'] ?? '')),
            'address' => trim((string) ($data['delivery_address'] ?? '')),
        ];
        if (mb_strlen($recipient['name']) < 2 || mb_strlen($recipient['phone']) < 6 || mb_strlen($recipient['address']) < 5) {
            throw DomainRuleException::make('delivery_details_required', 'Enter the name, phone number and address for delivery.');
        }

        return [null, $zone, $recipient];
    }

    // ---------------------------------------------------------------- Customer cancellation

    public function cancelByCustomer(Order $order, User $user, ?string $reason = null): Order
    {
        if ($order->user_id !== $user->id) {
            throw DomainRuleException::make('not_your_order', 'This order belongs to another account.', 403);
        }

        return DB::transaction(function () use ($order, $user, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->status->customerCancellable()) {
                throw DomainRuleException::make('order_not_cancellable', 'This order can no longer be cancelled in the app. Contact the store.', 409);
            }
            $this->releaseStock($order, $user->id, 'Cancelled by customer');
            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => 'customer',
                'cancel_reason' => filled($reason) ? mb_substr(trim($reason), 0, 500) : null,
            ]);
            Audit::record('order.cancelled', $order, new: ['by' => 'customer'], reason: $reason, actorId: $user->id);

            return $order;
        });
    }

    /** Returns every reserved item to stock. Callers hold the order lock inside a transaction. */
    public function releaseStock(Order $order, ?int $actorId, string $note): void
    {
        $items = OrderItem::where('order_id', $order->id)->whereNotNull('product_variant_id')->orderBy('product_variant_id')->get();
        $variants = ProductVariant::whereIn('id', $items->pluck('product_variant_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($items as $item) {
            $back = $item->quantity - $item->returned_quantity;
            if ($back > 0 && isset($variants[$item->product_variant_id])) {
                $this->inventory->move($variants[$item->product_variant_id], $back, InventoryReason::OrderCancelled, $order->id, $note, $actorId);
            }
        }
    }
}
