<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as its customer sees it. Only ever returned to the customer who placed it.
 * Staff notes and the identity of staff are not included.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'fulfillment' => $this->fulfillment->value,
            'pickup_location' => $this->pickupLocation ? [
                'name' => $this->pickupLocation->name,
                'address' => $this->pickupLocation->address,
                'opening_hours' => $this->pickupLocation->opening_hours,
            ] : null,
            'delivery' => $this->deliveryZone ? [
                'zone' => $this->deliveryZone->name,
                'estimate' => $this->deliveryZone->estimate,
                'recipient_name' => $this->recipient_name,
                'recipient_phone' => $this->recipient_phone,
                'address' => $this->delivery_address,
            ] : null,
            'note' => $this->customer_note,
            'currency' => $this->currency,
            'subtotal_minor' => $this->subtotal_minor,
            'delivery_fee_minor' => $this->delivery_fee_minor,
            'total_minor' => $this->total_minor,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status->value,
            'amount_refunded_minor' => $this->amount_refunded_minor,
            'payment_reference' => $this->payment_reference,
            'items' => $this->items->map(fn ($i) => [
                'product_name' => $i->product_name,
                'option_label' => $i->option_label,
                'unit_price_minor' => $i->unit_price_minor,
                'quantity' => $i->quantity,
                'line_total_minor' => $i->line_total_minor,
                'returned_quantity' => $i->returned_quantity,
                'seller_name' => $i->seller?->name,
                'product_slug' => $i->variant?->product?->slug,
                'image_url' => $i->variant?->product?->images->first()?->url(),
            ]),
            'item_count' => $this->items->sum('quantity'),
            'can_cancel' => $this->status->customerCancellable(),
            'cancel_reason' => $this->status === OrderStatus::Cancelled ? $this->cancel_reason : null,
            'cancelled_by' => $this->cancelled_by,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
