<?php

namespace App\Policies;

use App\Models\MallStore;
use App\Models\User;

class MallStorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor') || $user->hasRole('admin');
    }

    public function view(User $user, MallStore $store): bool
    {
        if ($store->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->mallStore === null;
    }

    public function update(User $user, MallStore $store): bool
    {
        if ($store->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin');
    }

    public function approve(User $user, MallStore $store): bool
    {
        return $user->hasRole('admin');
    }

    public function suspend(User $user, MallStore $store): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, MallStore $store): bool
    {
        return $user->hasRole('admin');
    }
}
