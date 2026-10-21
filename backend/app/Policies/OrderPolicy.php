<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/** Orders are placed by customers and changed only through OrderService actions. */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('store.manage');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('store.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
