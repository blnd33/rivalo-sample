<?php

namespace App\Policies;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;

/** Delivery areas and fees. Ones used by orders are deactivated, not deleted. */
class DeliveryZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('store.manage');
    }

    public function view(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can('store.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('store.manage');
    }

    public function update(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can('store.manage');
    }

    public function delete(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can('store.manage') && ! Order::where('delivery_zone_id', $deliveryZone->id)->exists();
    }
}
