<?php

namespace App\Policies;

use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tournaments.manage_all');
    }

    public function view(User $user, Season $season): bool
    {
        return $user->can('tournaments.manage_all');
    }

    public function create(User $user): bool
    {
        return $user->can('tournaments.manage_all');
    }

    public function update(User $user, Season $season): bool
    {
        return $user->can('tournaments.manage_all');
    }

    public function delete(User $user, Season $season): bool
    {
        return $user->can('tournaments.manage_all') && ! $season->tournaments()->exists();
    }
}
