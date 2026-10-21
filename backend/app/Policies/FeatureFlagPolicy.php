<?php

namespace App\Policies;

use App\Models\FeatureFlag;
use App\Models\User;

class FeatureFlagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('feature_flags.manage');
    }

    public function view(User $user, FeatureFlag $flag): bool
    {
        return $user->can('feature_flags.manage');
    }

    public function create(User $user): bool
    {
        return false; // Flags are defined in code/seeders, toggled in the panel.
    }

    public function update(User $user, FeatureFlag $flag): bool
    {
        return $user->can('feature_flags.manage');
    }

    public function delete(User $user, FeatureFlag $flag): bool
    {
        return false;
    }
}
