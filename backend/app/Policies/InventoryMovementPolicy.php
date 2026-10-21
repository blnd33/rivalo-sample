<?php

namespace App\Policies;

use App\Models\InventoryMovement;
use App\Models\User;
use App\Support\SellerContext;

/** Stock history is append-only. Sellers read the history of their own products' sizes. */
class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return SellerContext::current() ? true : $user->can('store.manage');
    }

    public function view(User $user, InventoryMovement $inventoryMovement): bool
    {
        if ($seller = SellerContext::current()) {
            return $inventoryMovement->variant()->whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))->exists();
        }

        return $user->can('store.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    public function delete(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }
}
