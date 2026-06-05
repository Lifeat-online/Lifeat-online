<?php

namespace App\Policies;

use App\Models\MallOrder;
use App\Models\User;

class MallOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support') || $this->isMallAdmin($user);
    }

    public function view(User $user, MallOrder $order): bool
    {
        if ($user->id === $order->customer_user_id) {
            return true;
        }

        if ($order->store?->owner_user_id === $user->id) {
            return true;
        }

        if ($this->isMallAdmin($user) || $user->hasRole('admin', 'editor', 'support')) {
            return true;
        }

        return false;
    }

    public function update(User $user, MallOrder $order): bool
    {
        if ($order->store?->owner_user_id === $user->id) {
            return true;
        }

        return $this->isMallAdmin($user) || $user->hasRole('admin', 'editor');
    }

    public function refund(User $user, MallOrder $order): bool
    {
        return $user->hasRole('admin') || $this->isMallAdmin($user);
    }

    public function delete(User $user, MallOrder $order): bool
    {
        return $user->hasRole('admin');
    }

    private function isMallAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
