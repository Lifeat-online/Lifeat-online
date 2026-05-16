<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAnyFinance(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support');
    }

    public function viewFinance(User $user, Payment $payment): bool
    {
        return $this->viewAnyFinance($user);
    }

    public function reconcile(User $user, Payment $payment): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->hasRole('admin');
    }
}
