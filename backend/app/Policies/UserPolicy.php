<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/** Admin-panel management of user accounts. Players manage their own account through /api/v1/me. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view') || $user->can('users.manage');
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false; // Accounts are created by players themselves; staff are promoted via role assignment.
    }

    public function update(User $user, User $model): bool
    {
        if ($model->hasRole(RoleName::SuperAdmin->value) && ! $user->hasRole(RoleName::SuperAdmin->value)) {
            return false;
        }

        return $user->can('users.manage');
    }

    public function assignRoles(User $user, User $model): bool
    {
        return $user->can('users.assign_roles') && $user->id !== $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return false; // Use the account deletion workflow (anonymization) instead of hard deletes.
    }
}
