<?php

namespace App\Models;

use App\Enums\LedgerFund;
use App\Enums\LedgerType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Append-only tournament money record. Positive = money in, negative = money out. Mistakes are
 * corrected with a reversal row, never by editing or deleting.
 */
#[Fillable([
    'tournament_id', 'type', 'fund', 'amount_minor', 'currency', 'reference', 'entry_payment_id',
    'sponsor_contribution_id', 'prize_id', 'reverses_id', 'transfer_group', 'payee', 'note',
    'recorded_by_id', 'recorded_at',
])]
class TournamentLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'fund' => LedgerFund::class,
            'amount_minor' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only. Record a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are never deleted.'));
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(SponsorContribution::class, 'sponsor_contribution_id');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(TournamentPrize::class, 'prize_id');
    }

    public function entryPayment(): BelongsTo
    {
        return $this->belongsTo(EntryPayment::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function formattedAmount(): string
    {
        return Money::format($this->amount_minor, $this->currency);
    }
}
