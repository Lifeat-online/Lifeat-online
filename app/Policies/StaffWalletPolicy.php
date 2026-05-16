<?php

namespace App\Policies;

use App\Models\StaffWallet;
use App\Models\User;

class StaffWalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support');
    }

    public function view(User $user, StaffWallet $wallet): bool
    {
        return $this->viewAny($user)
            || ((int) $wallet->user_id === (int) $user->id && $user->hasRole('staff'));
    }

    public function requestPayout(User $user, StaffWallet $wallet): bool
    {
        return (int) $wallet->user_id === (int) $user->id
            && $user->hasRole('staff');
    }
}
