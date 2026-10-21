<?php

namespace App\Models;

use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One fixture. (`Match` is a reserved word in PHP, hence the name.) */
#[Fillable([
    'tournament_id', 'stage_id', 'stage_group_id', 'round', 'position', 'label', 'status', 'scheduled_at',
    'location', 'next_match_id', 'next_slot', 'loser_next_match_id', 'loser_next_slot', 'winner_entry_id',
    'is_draw', 'approved_result_id',
])]
class TournamentMatch extends Model
{
    protected $table = 'matches';

    protected $attributes = ['status' => 'pending', 'is_draw' => false];

    protected function casts(): array
    {
        return ['status' => MatchStatus::class, 'scheduled_at' => 'datetime', 'is_draw' => 'boolean'];
    }

    /** "Final", "Round of 16", "Round 3"… stored in English, shown in the reader's language. */
    public static function translateLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }
        if (preg_match('/^Round of (\d+)$/', $label, $m)) {
            return __('Round of :count', ['count' => $m[1]]);
        }
        if (preg_match('/^Round (\d+)$/', $label, $m)) {
            return __('Round :round', ['round' => $m[1]]);
        }

        return __($label);
    }

    public function displayLabel(): string
    {
        return self::translateLabel($this->label ?? 'Round '.$this->round);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StageGroup::class, 'stage_group_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MatchParticipant::class, 'match_id')->orderBy('slot');
    }

    public function results(): HasMany
    {
        return $this->hasMany(MatchResult::class, 'match_id')->orderByDesc('version');
    }

    public function approvedResult(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class, 'approved_result_id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function loserNextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'loser_next_match_id');
    }

    public function winnerEntry(): BelongsTo
    {
        return $this->belongsTo(TournamentEntry::class, 'winner_entry_id');
    }

    public function entryInSlot(int $slot): ?TournamentEntry
    {
        return $this->participant($slot)?->entry;
    }

    public function entryIdInSlot(int $slot): ?int
    {
        return $this->participant($slot)?->tournament_entry_id;
    }

    private function participant(int $slot): ?MatchParticipant
    {
        return $this->relationLoaded('participants')
            ? $this->participants->firstWhere('slot', $slot)
            : $this->participants()->where('slot', $slot)->first();
    }

    /** Slot (1|2) of the entry a user plays for in this match, or null. */
    public function slotForUser(User $user): ?int
    {
        $this->loadMissing('participants');
        foreach ($this->participants as $p) {
            if ($p->tournament_entry_id && TournamentEntryMember::where('tournament_entry_id', $p->tournament_entry_id)
                ->where('user_id', $user->id)->where('is_active', true)->exists()) {
                return $p->slot;
            }
        }

        return null;
    }

    public function pendingResult(): ?MatchResult
    {
        return $this->results()->where('status', ResultStatus::Submitted->value)->first();
    }
}
