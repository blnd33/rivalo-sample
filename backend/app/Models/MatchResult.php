<?php

namespace App\Models;

use App\Enums\MatchOutcome;
use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One version of a match result. Versions are never edited after review; corrections add a new version. */
#[Fillable([
    'match_id', 'version', 'outcome', 'scores', 'winner_slot', 'status', 'submitted_by_id', 'submitted_as',
    'note', 'reviewed_by_id', 'reviewed_at', 'review_note',
])]
class MatchResult extends Model
{
    protected function casts(): array
    {
        return [
            'outcome' => MatchOutcome::class,
            'status' => ResultStatus::class,
            'scores' => 'array',
            'winner_slot' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(ResultDispute::class);
    }

    public function isDraw(): bool
    {
        return $this->winner_slot === null && $this->outcome !== MatchOutcome::Cancelled;
    }

    /** "2 – 1", "6-4 3-6 10-8", or the outcome name when no score applies. */
    public function scoreLine(): string
    {
        $s = $this->scores ?? [];
        if (! empty($s['sets'])) {
            return implode('  ', array_map(fn ($set) => $set[0].'-'.$set[1], $s['sets']));
        }
        if (isset($s['slot1'], $s['slot2'])) {
            return $s['slot1'].' – '.$s['slot2'];
        }

        return $this->outcome->label();
    }
}
