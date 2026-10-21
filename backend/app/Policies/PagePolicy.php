<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/** Policy pages: content editors edit and publish; only legal.record can record a legal review. */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.manage');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('content.manage');
    }

    public function create(User $user): bool
    {
        return false; // the three pages are created by PolicyPagesSeeder
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('content.manage');
    }

    public function delete(User $user, Page $page): bool
    {
        return false;
    }

    public function recordLegalReview(User $user, Page $page): bool
    {
        return $user->can('legal.record') && $page->requires_legal_review;
    }
}
