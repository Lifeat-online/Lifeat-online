<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support') || $user->hasRole('staff');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($invoice->user_id === $user->id) {
            return true;
        }

        if ($invoice->order?->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'support');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function send(User $user, Invoice $invoice): bool
    {
        if ($invoice->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor');
    }

    public function download(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
