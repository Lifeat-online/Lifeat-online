<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Article $article): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'writer');
    }

    public function update(User $user, Article $article): bool
    {
        if ($article->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function publish(User $user, Article $article): bool
    {
        if ($article->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function delete(User $user, Article $article): bool
    {
        if ($article->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }
}
