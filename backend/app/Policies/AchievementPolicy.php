<?php

namespace App\Policies;

use App\Models\Achievement;
use App\Models\User;

class AchievementPolicy
{
    private function allowed(User $user): bool
    {
        return $user->can('content.manage') || $user->can('points.manage');
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, Achievement $a): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, Achievement $a): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, Achievement $a): bool
    {
        // Badges people already hold are retired, not deleted.
        return $this->allowed($user) && ! $a->holders()->exists();
    }
}
