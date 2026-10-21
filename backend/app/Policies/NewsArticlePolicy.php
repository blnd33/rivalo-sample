<?php

namespace App\Policies;

use App\Enums\NewsStatus;
use App\Enums\Permission;
use App\Models\NewsArticle;
use App\Models\User;

/** News is written and published only by content editors (and super admins). */
class NewsArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function view(User $user, NewsArticle $article): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function update(User $user, NewsArticle $article): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function delete(User $user, NewsArticle $article): bool
    {
        // Published news is archived instead of deleted, so shared links keep resolving honestly.
        return $user->can(Permission::ContentManage->value) && $article->status !== NewsStatus::Published;
    }
}
