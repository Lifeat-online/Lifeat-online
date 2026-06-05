<?php

namespace App\Policies;

use App\Models\BrowserPushSubscription;
use App\Models\User;

class BrowserPushSubscriptionPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('admin', 'super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin', 'support', 'dev', 'developer');
    }

    public function view(User $user, BrowserPushSubscription $subscription): bool
    {
        return (int) $subscription->user_id === (int) $user->id
            || $user->hasRole('support', 'dev', 'developer');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, BrowserPushSubscription $subscription): bool
    {
        if ((int) $subscription->user_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('support');
    }
}
