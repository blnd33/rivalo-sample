<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/** Reports are filed through the API and handled through ModerationService, never edited directly. */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('moderation.manage');
    }

    public function view(User $user, Report $report): bool
    {
        return $user->can('moderation.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Report $report): bool
    {
        return false;
    }

    public function delete(User $user, Report $report): bool
    {
        return false;
    }
}
