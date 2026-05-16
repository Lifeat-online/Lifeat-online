<?php

namespace App\Policies;

use App\Models\PayoutRequest;
use App\Models\User;

class PayoutRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support');
    }

    public function view(User $user, PayoutRequest $payoutRequest): bool
    {
        return $this->viewAny($user)
            || ((int) $payoutRequest->requested_by_user_id === (int) $user->id && $user->hasRole('staff'));
    }

    public function cancel(User $user, PayoutRequest $payoutRequest): bool
    {
        return (int) $payoutRequest->requested_by_user_id === (int) $user->id
            && $user->hasRole('staff');
    }

    public function approve(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->hasRole('admin');
    }

    public function reject(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->hasRole('admin');
    }

    public function markPaid(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->hasRole('admin');
    }
}
