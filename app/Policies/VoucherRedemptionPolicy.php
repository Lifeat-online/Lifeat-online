<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoucherRedemption;

class VoucherRedemptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support') || $user->hasRole('staff');
    }

    public function view(User $user, VoucherRedemption $redemption): bool
    {
        if ($redemption->user_id === $user->id) {
            return true;
        }

        if ($redemption->voucher?->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'support', 'staff');
    }

    public function consume(User $user, VoucherRedemption $redemption): bool
    {
        if ($redemption->voucher?->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'staff');
    }
}
