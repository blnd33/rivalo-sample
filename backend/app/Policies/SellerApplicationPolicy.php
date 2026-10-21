<?php

namespace App\Policies;

use App\Models\SellerApplication;
use App\Models\User;
use App\Support\SellerContext;

/** Applications are submitted in the app and decided through SellerService. */
class SellerApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return ! SellerContext::current() && $user->can('sellers.manage');
    }

    public function view(User $user, SellerApplication $application): bool
    {
        return ! SellerContext::current() && $user->can('sellers.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SellerApplication $application): bool
    {
        return false;
    }

    public function delete(User $user, SellerApplication $application): bool
    {
        return false;
    }
}
