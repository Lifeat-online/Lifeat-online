<?php

namespace App\Policies;

use App\Models\CivicFaultReport;
use App\Models\User;

class CivicFaultReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function view(User $user, CivicFaultReport $report): bool
    {
        if ($report->reporter_user_id === $user->id) {
            return true;
        }

        if ($report->assigned_councillor_id && $user->id === $report->assignedCouncillor?->user_id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'councillor');
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, CivicFaultReport $report): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function moderate(User $user, CivicFaultReport $report): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function delete(User $user, CivicFaultReport $report): bool
    {
        return $user->hasRole('admin');
    }
}
