<?php

namespace App\Policies;

use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Support\SellerContext;

/** Seller statements are append-only: lines are added by SellerLedgerService, never edited. */
class SellerLedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return SellerContext::current() ? true : ($user->can('sellers.manage') || $user->can('finance.manage'));
    }

    public function view(User $user, SellerLedgerEntry $entry): bool
    {
        if ($seller = SellerContext::current()) {
            return $entry->seller_id === $seller->id;
        }

        return $user->can('sellers.manage') || $user->can('finance.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SellerLedgerEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, SellerLedgerEntry $entry): bool
    {
        return false;
    }
}
