<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\SellerLedgerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only seller statement line. Corrections are new lines (adjustments), never edits.
 * amount_minor > 0 increases what RIVALO owes the seller; payouts are negative.
 */
#[Fillable([
    'seller_id', 'type', 'currency', 'gross_minor', 'commission_minor', 'amount_minor', 'order_id', 'order_item_id', 'quantity',
    'payout_method', 'reference', 'note', 'idempotency_key', 'created_by_id', 'occurred_at',
])]
class SellerLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => SellerLedgerType::class,
            'payout_method' => PayoutMethod::class,
            'gross_minor' => 'integer',
            'commission_minor' => 'integer',
            'amount_minor' => 'integer',
            'quantity' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Seller statements are append-only.'));
        static::deleting(fn () => throw new LogicException('Seller statements are append-only.'));
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Payout receipt number shown on statements, e.g. PO-000012. */
    public function payoutNumber(): ?string
    {
        return $this->type === SellerLedgerType::Payout ? sprintf('PO-%06d', $this->id) : null;
    }
}
