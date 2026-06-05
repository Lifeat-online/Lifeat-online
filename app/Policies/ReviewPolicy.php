<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Review $review): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Review $review): bool
    {
        if ($review->reviewer_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function respond(User $user, Review $review): bool
    {
        if ($review->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function delete(User $user, Review $review): bool
    {
        if ($review->reviewer_user_id === $user->id) {
            return true;
        }

        if ($review->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }
}
