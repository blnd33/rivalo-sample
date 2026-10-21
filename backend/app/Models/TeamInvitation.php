<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'invited_user_id', 'invited_by_id', 'role', 'status', 'expires_at', 'responded_at'])]
class TeamInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isOpen(): bool
    {
        return $this->status === InvitationStatus::Pending && $this->expires_at->isFuture();
    }
}
