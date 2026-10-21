<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/**
 * Captain abilities are scoped to the captain's own team. Staff moderation uses explicit permissions.
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('teams.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->can('teams.view') || $team->hasActiveMember($user);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail();
    }

    /** Rename, upload logo, invite, remove members, change roles, transfer captaincy, disband. */
    public function manage(User $user, Team $team): bool
    {
        return $team->isActive() && $team->isCaptain($user) && $user->isActive();
    }

    public function update(User $user, Team $team): bool
    {
        return $this->manage($user, $team) || $user->can('teams.moderate');
    }

    public function moderate(User $user, Team $team): bool
    {
        return $user->can('teams.moderate');
    }

    public function delete(User $user, Team $team): bool
    {
        return false; // Teams are disbanded, never deleted, to keep history.
    }
}
