<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function manage(User $user, Order $order): bool
    {
        return (int) $order->user_id === (int) $user->id
            || $user->hasRole('admin', 'staff');
    }

    public function viewAnyFinance(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support');
    }

    public function viewFinance(User $user, Order $order): bool
    {
        return $this->viewAnyFinance($user);
    }

    public function exportFinance(User $user): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function setAttribution(User $user, Order $order): bool
    {
        return $user->hasRole('admin', 'editor');
    }
}
