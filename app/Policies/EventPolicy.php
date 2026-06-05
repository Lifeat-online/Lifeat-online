<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Event $event): bool
    {
        if ($event->listing?->owner_user_id === $user->id) {
            return true;
        }

        if ($event->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function delete(User $user, Event $event): bool
    {
        if ($event->listing?->owner_user_id === $user->id) {
            return true;
        }

        if ($event->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }
}
