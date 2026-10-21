<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Sponsor;
use App\Models\User;

/** Sponsors (including their private contact details) are managed by finance managers only. */
class SponsorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FinanceManage->value);
    }

    public function view(User $user, Sponsor $sponsor): bool
    {
        return $user->can(Permission::FinanceManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FinanceManage->value);
    }

    public function update(User $user, Sponsor $sponsor): bool
    {
        return $user->can(Permission::FinanceManage->value);
    }

    public function delete(User $user, Sponsor $sponsor): bool
    {
        // Sponsors with contribution history are deactivated instead, so records stay complete.
        return $user->can(Permission::FinanceManage->value) && ! $sponsor->contributions()->exists();
    }
}
