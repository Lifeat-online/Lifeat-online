<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestTrackingController extends Controller
{
    public function show(Request $request, TransportRequest $transportRequest): JsonResponse
    {
        $this->authorizeTracking($request, $transportRequest);

        $transportRequest->loadMissing(['acceptedDriver.activeDutySession', 'acceptedVehicle']);
        $driverSession = $transportRequest->acceptedDriver?->activeDutySession;

        $driverLocation = $this->locationPayload(
            $driverSession?->last_latitude,
            $driverSession?->last_longitude,
            $driverSession?->last_seen_at?->toIso8601String(),
        );
        $passengerLocation = $this->locationPayload(
            $transportRequest->passenger_latitude,
            $transportRequest->passenger_longitude,
            $transportRequest->passenger_location_seen_at?->toIso8601String(),
        );
        $pickup = $this->locationPayload($transportRequest->pickup_latitude, $transportRequest->pickup_longitude);

        return response()->json([
            'request_id' => $transportRequest->id,
            'status' => $transportRequest->status,
            'pickup' => $pickup,
            'dropoff' => $this->locationPayload($transportRequest->dropoff_latitude, $transportRequest->dropoff_longitude),
            'driver' => [
                'name' => $transportRequest->acceptedDriver?->user?->name,
                'vehicle' => $transportRequest->acceptedVehicle?->name,
                'location' => $driverLocation,
                'distance_to_pickup_km' => $driverLocation && $pickup ? round($this->distanceKm($driverLocation, $pickup), 1) : null,
                'distance_to_passenger_km' => $driverLocation && $passengerLocation ? round($this->distanceKm($driverLocation, $passengerLocation), 1) : null,
            ],
            'passenger' => [
                'name' => $transportRequest->user?->name,
                'location' => $passengerLocation,
            ],
        ]);
    }

    public function updatePassenger(Request $request, TransportRequest $transportRequest): JsonResponse
    {
        abort_unless($transportRequest->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $transportRequest->update([
            'passenger_latitude' => $data['latitude'],
            'passenger_longitude' => $data['longitude'],
            'passenger_location_seen_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function updateDriver(Request $request, TransportRequest $transportRequest): JsonResponse
    {
        $driver = $request->user()->transportDriver;

        abort_unless($driver && $transportRequest->accepted_transport_driver_id === $driver->id, 403);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        TransportDutySession::where('transport_driver_id', $driver->id)
            ->whereNull('ended_at')
            ->update([
                'last_latitude' => $data['latitude'],
                'last_longitude' => $data['longitude'],
                'last_seen_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    private function authorizeTracking(Request $request, TransportRequest $transportRequest): void
    {
        $user = $request->user();
        $driver = $user->transportDriver;

        abort_unless(
            $transportRequest->user_id === $user->id
            || ($driver && $transportRequest->accepted_transport_driver_id === $driver->id)
            || $user->hasRole('transport_manager', 'admin', 'support'),
            403,
        );
    }

    private function locationPayload(mixed $latitude, mixed $longitude, ?string $seenAt = null): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'lat' => (float) $latitude,
            'lng' => (float) $longitude,
            'seen_at' => $seenAt,
        ];
    }

    private function distanceKm(array $from, array $to): float
    {
        $radiusKm = 6371;
        $dLat = deg2rad($to['lat'] - $from['lat']);
        $dLng = deg2rad($to['lng'] - $from['lng']);
        $lat1 = deg2rad($from['lat']);
        $lat2 = deg2rad($to['lat']);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return $radiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
