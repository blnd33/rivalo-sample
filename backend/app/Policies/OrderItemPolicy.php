<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;
use App\Support\SellerContext;

/**
 * Order lines are read-only. Sellers see their own lines in the Seller Hub ("Sales"), never the
 * customer or delivery details. Staff see orders through the Orders screen.
 */
class OrderItemPolicy
{
    public function viewAny(User $user): bool
    {
        return SellerContext::current() ? true : $user->can('store.manage');
    }

    public function view(User $user, OrderItem $item): bool
    {
        if ($seller = SellerContext::current()) {
            return $item->seller_id === $seller->id;
        }

        return $user->can('store.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OrderItem $item): bool
    {
        return false;
    }

    public function delete(User $user, OrderItem $item): bool
    {
        return false;
    }
}
