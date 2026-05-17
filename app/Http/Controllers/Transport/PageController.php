<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function __invoke(): View
    {
        $activeSessions = TransportDutySession::query()
            ->with(['driver.user', 'vehicle'])
            ->whereNull('ended_at')
            ->whereIn('status', [
                TransportDutySession::STATUS_AVAILABLE,
                TransportDutySession::STATUS_BUSY,
            ])
            ->whereHas('driver', fn ($query) => $query->where('status', TransportDriver::STATUS_APPROVED))
            ->latest('last_seen_at')
            ->get();

        $driverMapMarkers = $activeSessions
            ->filter(fn (TransportDutySession $session): bool => $session->last_latitude !== null && $session->last_longitude !== null)
            ->map(fn (TransportDutySession $session): array => [
                'lat' => (float) $session->last_latitude,
                'lng' => (float) $session->last_longitude,
                'title' => $session->driver?->user?->name ?: 'Online driver',
                'status' => $session->status,
                'status_label' => $session->status === TransportDutySession::STATUS_BUSY ? 'Occupied' : 'Available',
                'vehicle' => $session->vehicle?->name ?: ucfirst((string) ($session->vehicle?->vehicle_type ?: 'vehicle')),
                'seen' => $session->last_seen_at?->diffForHumans(),
                'marker_class' => $session->status === TransportDutySession::STATUS_BUSY
                    ? 'life-marker-status-busy'
                    : 'life-marker-status-available',
            ])
            ->values()
            ->all();

        return view('transport.index', [
            'activeDriverSessions' => $activeSessions,
            'driverMapMarkers' => $driverMapMarkers,
            'availableDriverCount' => $activeSessions->where('status', TransportDutySession::STATUS_AVAILABLE)->count(),
            'occupiedDriverCount' => $activeSessions->where('status', TransportDutySession::STATUS_BUSY)->count(),
        ]);
    }
}
