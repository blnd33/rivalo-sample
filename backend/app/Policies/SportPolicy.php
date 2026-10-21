<?php

namespace App\Policies;

use App\Models\Sport;
use App\Models\User;

class SportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function view(User $user, Sport $sport): bool
    {
        return $user->can('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, Sport $sport): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, Sport $sport): bool
    {
        return $user->can('catalog.manage') && ! $sport->disciplines()->exists();
    }
}
