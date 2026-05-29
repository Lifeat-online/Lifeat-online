<?php

use App\Models\TransportRequest;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('transport.driver.{driverId}', function ($user, int $driverId) {
    return $user->transportDriver?->id === $driverId || $user->hasRole('transport_manager', 'admin', 'support', 'dev');
});

Broadcast::channel('transport.request.{requestId}', function ($user, int $requestId) {
    $transportRequest = TransportRequest::find($requestId);

    if (! $transportRequest) {
        return false;
    }

    return $transportRequest->user_id === $user->id
        || $transportRequest->acceptedDriver?->user_id === $user->id
        || $user->hasRole('transport_manager', 'admin', 'support', 'dev');
});

Broadcast::channel('transport.manager', function ($user) {
    return $user->hasRole('transport_manager', 'admin', 'support', 'dev');
});
