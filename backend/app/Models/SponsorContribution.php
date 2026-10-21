<?php

namespace App\Models;

use App\Enums\ContributionRestriction;
use App\Enums\ContributionStatus;
use App\Enums\ContributionType;
use App\Enums\LedgerType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a sponsor committed to a tournament. Cash actually received is recorded in the ledger
 * (type sponsor_cash), never assumed from the pledge.
 */
#[Fillable([
    'sponsor_id', 'tournament_id', 'type', 'restriction', 'committed_minor', 'currency',
    'in_kind_description', 'status', 'note', 'cancel_reason', 'created_by_id',
])]
class SponsorContribution extends Model
{
    protected $attributes = [
        'status' => 'pledged',
        'committed_minor' => 0,
        'is_sample_data' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ContributionType::class,
            'restriction' => ContributionRestriction::class,
            'status' => ContributionStatus::class,
            'committed_minor' => 'integer',
            'is_sample_data' => 'boolean',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TournamentLedgerEntry::class);
    }

    /** Cash received so far, net of reversed receipts. */
    public function receivedMinor(): int
    {
        // Receipts and their reversals both carry this contribution's id.
        return (int) $this->ledgerEntries()
            ->whereIn('type', [LedgerType::SponsorCash->value, LedgerType::Reversal->value])
            ->sum('amount_minor');
    }

    public function isCash(): bool
    {
        return $this->type === ContributionType::Cash;
    }

    public function summary(): string
    {
        if ($this->isCash()) {
            return Money::format($this->committed_minor, $this->currency).' · '.$this->restriction?->label();
        }

        return $this->type->label().': '.($this->in_kind_description ?? '—');
    }
}
