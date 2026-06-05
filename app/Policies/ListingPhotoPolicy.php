<?php

namespace App\Policies;

use App\Models\ListingPhoto;
use App\Models\User;

class ListingPhotoPolicy
{
    public function view(User $user, ListingPhoto $photo): bool
    {
        if ($photo->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function create(User $user, ListingPhoto $photo): bool
    {
        if ($photo->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function update(User $user, ListingPhoto $photo): bool
    {
        if ($photo->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function delete(User $user, ListingPhoto $photo): bool
    {
        if ($photo->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function makePrimary(User $user, ListingPhoto $photo): bool
    {
        if ($photo->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }
}
