<?php

namespace App\Models;

use App\Enums\Fulfillment;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A store order. Totals, prices and product names are fixed when it is placed. Delivery details
 * are private to the customer and store staff.
 */
#[Fillable([
    'number', 'user_id', 'status', 'fulfillment', 'pickup_location_id', 'delivery_zone_id', 'recipient_name', 'recipient_phone',
    'delivery_address', 'customer_note', 'currency', 'subtotal_minor', 'delivery_fee_minor', 'total_minor', 'payment_method',
    'payment_status', 'amount_paid_minor', 'amount_refunded_minor', 'payment_reference', 'paid_at', 'paid_recorded_by_id',
    'placed_at', 'confirmed_at', 'ready_at', 'completed_at', 'cancelled_at', 'cancel_reason', 'cancelled_by',
])]
#[Hidden(['recipient_phone', 'delivery_address'])]
class Order extends Model
{
    protected $attributes = ['status' => 'placed', 'payment_status' => 'unpaid', 'amount_paid_minor' => 0, 'amount_refunded_minor' => 0, 'delivery_fee_minor' => 0, 'is_sample_data' => false];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fulfillment' => Fulfillment::class,
            'payment_status' => OrderPaymentStatus::class,
            'subtotal_minor' => 'integer',
            'delivery_fee_minor' => 'integer',
            'total_minor' => 'integer',
            'amount_paid_minor' => 'integer',
            'amount_refunded_minor' => 'integer',
            'paid_at' => 'datetime',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_sample_data' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        // chaperone(): each item knows its order without another query.
        return $this->hasMany(OrderItem::class)->chaperone('order');
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
