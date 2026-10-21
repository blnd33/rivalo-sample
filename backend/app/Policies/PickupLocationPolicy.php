<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\User;

/** Pickup points. Ones used by orders are deactivated, not deleted. */
class PickupLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('store.manage');
    }

    public function view(User $user, PickupLocation $pickupLocation): bool
    {
        return $user->can('store.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('store.manage');
    }

    public function update(User $user, PickupLocation $pickupLocation): bool
    {
        return $user->can('store.manage');
    }

    public function delete(User $user, PickupLocation $pickupLocation): bool
    {
        return $user->can('store.manage') && ! Order::where('pickup_location_id', $pickupLocation->id)->exists();
    }
}
