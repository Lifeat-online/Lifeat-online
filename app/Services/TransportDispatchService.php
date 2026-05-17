<?php

namespace App\Services;

use App\Events\TransportRequestOffered;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use App\Models\TransportRequestOffer;
use App\Models\TransportVehicle;
use Illuminate\Support\Collection;

class TransportDispatchService
{
    public function __construct(private readonly TransportFareService $fareService)
    {
    }

    public function createOffers(TransportRequest $request): Collection
    {
        $sessions = TransportDutySession::query()
            ->with(['driver.user', 'vehicle'])
            ->whereNull('ended_at')
            ->where('status', TransportDutySession::STATUS_AVAILABLE)
            ->whereHas('driver', function ($query) use ($request) {
                $query->where('status', TransportDriver::STATUS_APPROVED);

                if ($request->service_type === 'ride') {
                    $query->where('can_transport_people', true);
                } else {
                    $query->where('can_transport_parcels', true);
                }
            })
            ->whereHas('vehicle', function ($query) use ($request) {
                $query->where('status', TransportVehicle::STATUS_APPROVED);

                if ($request->service_type === 'ride') {
                    $query->where('can_carry_people', true)
                        ->where('max_passengers', '>=', $request->passenger_count);
                } else {
                    $query->where('can_carry_parcels', true);
                    if ($request->parcel_weight_kg !== null) {
                        $query->where(function ($weightQuery) use ($request) {
                            $weightQuery->whereNull('max_weight_kg')
                                ->orWhere('max_weight_kg', '>=', $request->parcel_weight_kg);
                        });
                    }
                }

                if ($request->required_vehicle_type) {
                    $query->where('vehicle_type', $request->required_vehicle_type);
                }

                match ($request->payment_method) {
                    'cash' => $query->where('accepts_cash', true),
                    'card_machine' => $query->where('has_card_machine', true),
                    default => $query->where('accepts_payfast', true),
                };
            })
            ->limit(20)
            ->get();

        return $sessions->map(function (TransportDutySession $session) use ($request) {
            $fare = $this->fareService->estimate(
                $session->vehicle,
                (float) $request->distance_km,
                (int) $request->passenger_count,
            );

            $offer = TransportRequestOffer::firstOrCreate(
                [
                    'transport_request_id' => $request->id,
                    'transport_driver_id' => $session->transport_driver_id,
                ],
                [
                    'transport_vehicle_id' => $session->transport_vehicle_id,
                    'transport_duty_session_id' => $session->id,
                    'status' => TransportRequestOffer::STATUS_OFFERED,
                    'quoted_amount' => $fare['quoted_amount'],
                    'platform_fee' => $fare['platform_fee'],
                    'driver_amount' => $fare['driver_amount'],
                    'offered_at' => now(),
                ],
            );

            if ($offer->wasRecentlyCreated) {
                event(new TransportRequestOffered($offer));
            }

            return $offer;
        });
    }
}
