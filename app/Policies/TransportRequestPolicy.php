<?php

namespace App\Policies;

use App\Models\TransportRequest;
use App\Models\User;

class TransportRequestPolicy
{
    public function view(User $user, TransportRequest $request): bool
    {
        if ($request->passenger_user_id === $user->id) {
            return true;
        }

        if ($request->assigned_driver_id && $request->driver?->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'transport_manager', 'transport_driver');
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function cancel(User $user, TransportRequest $request): bool
    {
        if ($request->passenger_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'transport_manager');
    }

    public function track(User $user, TransportRequest $request): bool
    {
        return $this->view($user, $request);
    }

    public function updateDriverLocation(User $user, TransportRequest $request): bool
    {
        if ($request->assigned_driver_id && $request->driver?->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'transport_manager');
    }
}
