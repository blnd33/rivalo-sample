<?php

namespace App\Models;

use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReportSubject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's report about another player, a team, or the app itself.
 * The reporter's identity and the staff resolution note are never shown to the reported player.
 */
#[Fillable([
    'reporter_id', 'subject_type', 'subject_user_id', 'subject_team_id', 'dedupe_key', 'reason', 'details',
    'snapshot', 'context', 'status', 'action', 'resolution_note', 'handled_by_id', 'handled_at',
])]
class Report extends Model
{
    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'subject_type' => ReportSubject::class,
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'action' => ModerationAction::class,
            'snapshot' => 'array',
            'context' => 'array',
            'handled_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function subjectTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'subject_team_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', ReportStatus::Open->value);
    }

    public function isOpen(): bool
    {
        return $this->status === ReportStatus::Open;
    }

    /** Short description of what was reported, for admin lists. */
    public function subjectLabel(): string
    {
        return match ($this->subject_type) {
            ReportSubject::User => '@'.($this->snapshot['username'] ?? '?'),
            ReportSubject::Team => (string) ($this->snapshot['name'] ?? '?'),
            ReportSubject::App => __('The app'),
        };
    }
}
