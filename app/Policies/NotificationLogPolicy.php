<?php

namespace App\Policies;

use App\Models\NotificationLog;
use App\Models\User;

class NotificationLogPolicy
{
    public function viewAnyFinance(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support');
    }

    public function viewFinance(User $user, NotificationLog $notificationLog): bool
    {
        return $this->viewAnyFinance($user);
    }

    public function resend(User $user, NotificationLog $notificationLog): bool
    {
        return $user->hasRole('admin', 'editor');
    }
}
