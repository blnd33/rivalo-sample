<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An in-person payment or refund recorded by staff. Append-only: only the void fields may be
 * set (once), and rows are never deleted.
 */
#[Fillable([
    'tournament_entry_id', 'tournament_id', 'kind', 'method', 'amount_minor', 'currency',
    'reference', 'note', 'recorded_by_id', 'recorded_at', 'voided_at', 'voided_by_id', 'void_reason',
])]
class EntryPayment extends Model
{
    public const KIND_PAYMENT = 'payment';

    public const KIND_REFUND = 'refund';

    private const VOID_FIELDS = ['voided_at', 'voided_by_id', 'void_reason', 'updated_at'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (EntryPayment $payment) {
            $changed = array_keys($payment->getDirty());
            if (array_diff($changed, self::VOID_FIELDS) || $payment->getOriginal('voided_at') !== null) {
                throw new LogicException('Payments are append-only. Void and record again instead of editing.');
            }
        });
        static::deleting(fn () => throw new LogicException('Payments are never deleted.'));
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TournamentEntry::class, 'tournament_entry_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_id');
    }

    public function scopeValid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isRefund(): bool
    {
        return $this->kind === self::KIND_REFUND;
    }

    public function formattedAmount(): string
    {
        return Money::format($this->amount_minor, $this->currency);
    }
}
