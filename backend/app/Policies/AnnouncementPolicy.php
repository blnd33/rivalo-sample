<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('announcements.send');
    }

    public function view(User $user, Announcement $a): bool
    {
        return $user->can('announcements.send');
    }

    public function create(User $user): bool
    {
        return $user->can('announcements.send');
    }

    public function update(User $user, Announcement $a): bool
    {
        return $user->can('announcements.send') && $a->isEditable();
    }

    public function delete(User $user, Announcement $a): bool
    {
        return $user->can('announcements.send') && $a->status->value === 'draft';
    }
}
