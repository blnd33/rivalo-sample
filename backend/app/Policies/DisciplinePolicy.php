<?php

namespace App\Policies;

use App\Models\Discipline;
use App\Models\User;

class DisciplinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function view(User $user, Discipline $discipline): bool
    {
        return $user->can('catalog.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user, Discipline $discipline): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user, Discipline $discipline): bool
    {
        return $user->can('catalog.manage') && ! $discipline->tournaments()->exists();
    }
}
