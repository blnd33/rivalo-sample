<?php

namespace App\Models;

use App\Enums\PrizeBasis;
use App\Enums\PrizeLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One prize tier (1st, 2nd, …). Amounts for pool shares are computed by FinanceService. */
#[Fillable(['tournament_id', 'place', 'title', 'basis', 'amount_minor', 'pool_share_bp', 'in_kind_description', 'label', 'backing_contribution_id'])]
class TournamentPrize extends Model
{
    protected $attributes = ['label' => 'estimated', 'basis' => 'fixed'];

    protected function casts(): array
    {
        return [
            'basis' => PrizeBasis::class,
            'label' => PrizeLabel::class,
            'place' => 'integer',
            'amount_minor' => 'integer',
            'pool_share_bp' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function backingContribution(): BelongsTo
    {
        return $this->belongsTo(SponsorContribution::class, 'backing_contribution_id');
    }

    public function displayTitle(): string
    {
        return $this->title ?: __('Place :place', ['place' => $this->place]);
    }
}
