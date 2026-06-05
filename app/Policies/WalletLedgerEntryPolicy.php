<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WalletLedgerEntry;

class WalletLedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support') || $user->hasRole('staff');
    }

    public function view(User $user, WalletLedgerEntry $entry): bool
    {
        if ($entry->wallet?->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'support');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WalletLedgerEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, WalletLedgerEntry $entry): bool
    {
        return $user->hasRole('admin');
    }
}
