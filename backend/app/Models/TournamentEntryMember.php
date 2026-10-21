<?php

namespace App\Models;

use App\Enums\EntryMemberRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster snapshot row. Names are copied at registration so later profile/team changes
 * do not rewrite tournament history.
 */
#[Fillable([
    'tournament_entry_id', 'tournament_id', 'user_id', 'role', 'username_snapshot',
    'display_name_snapshot', 'is_active', 'removed_at',
])]
class TournamentEntryMember extends Model
{
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'role' => EntryMemberRole::class,
            'is_active' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TournamentEntry::class, 'tournament_entry_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
