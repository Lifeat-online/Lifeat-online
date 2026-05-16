<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function manage(User $user, Subscription $subscription): bool
    {
        return (int) $subscription->user_id === (int) $user->id
            || $user->hasRole('admin', 'staff');
    }

    public function viewAnyFinance(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support');
    }

    public function viewFinance(User $user, Subscription $subscription): bool
    {
        return $this->viewAnyFinance($user);
    }

    public function extend(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function suspend(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin');
    }

    public function sendReminder(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin', 'editor');
    }
}
