<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['match_result_id', 'match_id', 'tournament_entry_id', 'raised_by_id', 'reason', 'status', 'resolved_by_id', 'resolved_at', 'resolution_note'])]
class ResultDispute extends Model
{
    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['status' => DisputeStatus::class, 'resolved_at' => 'datetime'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class, 'match_result_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TournamentEntry::class, 'tournament_entry_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_id');
    }
}
