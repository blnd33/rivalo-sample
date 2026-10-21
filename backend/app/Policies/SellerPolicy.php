<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\User;
use App\Support\SellerContext;

/**
 * Shops. Staff with sellers.manage review and suspend them (finance staff record payouts);
 * shops are created only by approving an application and never deleted (statements are kept).
 * In the Seller Hub a seller edits only their own public profile.
 */
class SellerPolicy
{
    public function viewAny(User $user): bool
    {
        return SellerContext::current() ? false : ($user->can('sellers.manage') || $user->can('finance.manage'));
    }

    public function view(User $user, Seller $seller): bool
    {
        if ($own = SellerContext::current()) {
            return $own->id === $seller->id;
        }

        return $user->can('sellers.manage') || $user->can('finance.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Seller $seller): bool
    {
        if ($own = SellerContext::current()) {
            return $own->id === $seller->id && SellerContext::canWrite();
        }

        return $user->can('sellers.manage') && $seller->user_id !== $user->id;
    }

    public function delete(User $user, Seller $seller): bool
    {
        return false;
    }
}
