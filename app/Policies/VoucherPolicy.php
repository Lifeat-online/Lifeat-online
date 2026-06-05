<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Voucher;

class VoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Voucher $voucher): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Voucher $voucher): bool
    {
        if ($voucher->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'staff');
    }

    public function delete(User $user, Voucher $voucher): bool
    {
        if ($voucher->listing?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'staff');
    }

    public function redeem(User $user, Voucher $voucher): bool
    {
        return $user !== null;
    }
}
