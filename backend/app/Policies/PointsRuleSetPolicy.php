<?php

namespace App\Policies;

use App\Models\PointsRuleSet;
use App\Models\User;

/** Points tables: points managers only. Published tables are read-only (enforced by the model too). */
class PointsRuleSetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('points.manage');
    }

    public function view(User $user, PointsRuleSet $set): bool
    {
        return $user->can('points.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('points.manage');
    }

    public function update(User $user, PointsRuleSet $set): bool
    {
        return $user->can('points.manage');
    }

    public function delete(User $user, PointsRuleSet $set): bool
    {
        return $user->can('points.manage') && $set->isDraft();
    }
}
