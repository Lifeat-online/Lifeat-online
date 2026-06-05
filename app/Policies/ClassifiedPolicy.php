<?php

namespace App\Policies;

use App\Models\Classified;
use App\Models\User;

class ClassifiedPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Classified $classified): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Classified $classified): bool
    {
        if ($classified->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function review(User $user, Classified $classified): bool
    {
        return $user->hasRole('admin', 'editor', 'staff');
    }

    public function delete(User $user, Classified $classified): bool
    {
        if ($classified->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }
}
