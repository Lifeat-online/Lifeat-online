<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WriterPaymentBatch;

class WriterPaymentBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function view(User $user, WriterPaymentBatch $batch): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function markPaid(User $user, WriterPaymentBatch $batch): bool
    {
        return $user->hasRole('admin');
    }

    public function export(User $user, WriterPaymentBatch $batch): bool
    {
        return $user->hasRole('admin', 'editor');
    }
}
