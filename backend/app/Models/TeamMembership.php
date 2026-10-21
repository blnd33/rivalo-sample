<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stint of a user in a team. Rows are never deleted, so the table doubles as membership history.
 */
#[Fillable(['team_id', 'user_id', 'role', 'status', 'joined_at', 'left_at', 'left_reason'])]
class TeamMembership extends Model
{
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
