<?php

namespace App\Services;

use App\Events\TransportRequestStatusChanged;
use App\Models\MallFulfillment;
use App\Models\MallOrder;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use App\Models\TransportVehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MallTransportDeliveryService
{
    public function __construct(
        private readonly TransportFareService $fareService,
        private readonly TransportDispatchService $dispatchService
    ) {}

    public function pricingVehicles(): array
    {
        return $this->eligibleSessions()
            ->map(fn (TransportDutySession $session) => $this->pricingVehiclePayload($session))
            ->filter()
            ->values()
            ->all();
    }

    public function pricingVehicleTypes(): array
    {
        return collect($this->pricingVehicles())
            ->pluck('vehicle_type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function quote(array $deliveryData): array
    {
        $distanceKm = (float) ($deliveryData['delivery_distance_km'] ?? 0);

        if ($distanceKm <= 0) {
            throw ValidationException::withMessages([
                'delivery_address' => 'Select a delivery address so the taxi distance can be calculated.',
            ]);
        }

        $bestQuote = $this->eligibleSessions(
            $deliveryData['parcel_weight_kg'] ?? null,
            $deliveryData['required_vehicle_type'] ?? null
        )
            ->map(function (TransportDutySession $session) use ($distanceKm) {
                $fare = $this->fareService->estimate($session->vehicle, $distanceKm, 0);

                return [
                    'session' => $session,
                    'fare' => $fare,
                ];
            })
            ->sortBy(fn (array $quote) => $quote['fare']['quoted_amount'])
            ->first();

        if (! $bestQuote) {
            throw ValidationException::withMessages([
                'delivery_method' => 'No active taxi delivery vehicles can quote this mall delivery right now.',
            ]);
        }

        /** @var TransportDutySession $session */
        $session = $bestQuote['session'];
        $fare = $bestQuote['fare'];

        return [
            'delivery_fee' => $this->money($fare['quoted_amount']),
            'platform_fee' => $this->money($fare['platform_fee']),
            'provider_amount' => $this->money($fare['driver_amount']),
            'meta' => [
                'transport_quote' => [
                    'delivery_distance_km' => $distanceKm,
                    'parcel_weight_kg' => $deliveryData['parcel_weight_kg'] ?? null,
                    'required_vehicle_type' => $deliveryData['required_vehicle_type'] ?? null,
                    'quoted_vehicle_id' => $session->transport_vehicle_id,
                    'quoted_driver_id' => $session->transport_driver_id,
                    'vehicle_name' => $session->vehicle?->name,
                    'vehicle_type' => $session->vehicle?->vehicle_type,
                ],
            ],
        ];
    }

    public function dispatchPaidOrder(MallOrder $order): ?TransportRequest
    {
        $order->loadMissing('store', 'fulfillment');
        $fulfillment = $order->fulfillment;

        if (! $fulfillment || $fulfillment->provider !== 'taxi') {
            return null;
        }

        if ($fulfillment->external_type === 'transport_request' && $fulfillment->external_id) {
            return TransportRequest::find($fulfillment->external_id);
        }

        $fulfillmentMeta = $fulfillment->meta ?? [];
        $quoteMeta = $fulfillmentMeta['transport_quote'] ?? [];
        $distanceKm = (float) ($quoteMeta['delivery_distance_km'] ?? 0);
        $pickupAddress = ($fulfillmentMeta['pickup_address'] ?? $order->store->pickup_address) ?: 'Mall pickup: '.$order->store->name;

        $transportRequest = TransportRequest::create([
            'user_id' => $order->user_id,
            'request_number' => $this->nextRequestNumber(),
            'service_type' => 'parcel',
            'status' => TransportRequest::STATUS_DISPATCHING,
            'payment_method' => 'payfast',
            'request_timing' => 'immediate',
            'dispatch_started_at' => now(),
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $fulfillment->delivery_address ?: 'Mall customer delivery address',
            'pickup_latitude' => $this->nullableFloat($fulfillmentMeta['pickup_latitude'] ?? $order->store->pickup_latitude),
            'pickup_longitude' => $this->nullableFloat($fulfillmentMeta['pickup_longitude'] ?? $order->store->pickup_longitude),
            'dropoff_latitude' => $this->nullableFloat($fulfillmentMeta['delivery_latitude'] ?? null),
            'dropoff_longitude' => $this->nullableFloat($fulfillmentMeta['delivery_longitude'] ?? null),
            'distance_km' => max(0.1, $distanceKm),
            'passenger_count' => 0,
            'parcel_weight_kg' => $quoteMeta['parcel_weight_kg'] ?? null,
            'required_vehicle_type' => $quoteMeta['required_vehicle_type'] ?? null,
            'client_notes' => trim('Mall order '.$order->order_number.'. '.$order->customer_notes),
            'quoted_amount' => $fulfillment->delivery_fee,
            'platform_fee' => $fulfillment->platform_fee,
            'driver_amount' => $fulfillment->provider_amount,
        ]);

        $offers = $this->dispatchService->createOffers($transportRequest);
        $bestOffer = $offers->sortBy('quoted_amount')->first();

        if ($bestOffer) {
            $transportRequest->update([
                'quoted_amount' => $bestOffer->quoted_amount,
                'platform_fee' => $bestOffer->platform_fee,
                'driver_amount' => $bestOffer->driver_amount,
            ]);
        } else {
            $transportRequest->update([
                'status' => TransportRequest::STATUS_SCHEDULED,
                'request_timing' => 'scheduled',
                'scheduled_pickup_at' => now()->addHour(),
            ]);
        }

        $transportRequest = $transportRequest->fresh();
        $transportRequest->statusEvents()->create([
            'actor_user_id' => $order->user_id,
            'status' => $transportRequest->status,
            'notes' => $offers->isEmpty()
                ? 'Mall taxi delivery held because no eligible driver is online.'
                : 'Mall taxi delivery dispatched to active drivers.',
        ]);

        if ($offers->isNotEmpty()) {
            event(new TransportRequestStatusChanged($transportRequest, 'Mall delivery dispatched to active drivers.'));
        }

        $this->linkFulfillment($fulfillment, $transportRequest, $offers);

        return $transportRequest;
    }

    private function eligibleSessions(string|int|float|null $parcelWeightKg = null, ?string $requiredVehicleType = null): Collection
    {
        return TransportDutySession::query()
            ->with(['driver.user', 'vehicle'])
            ->whereNull('ended_at')
            ->where('status', TransportDutySession::STATUS_AVAILABLE)
            ->whereHas('driver', fn ($query) => $query
                ->where('status', TransportDriver::STATUS_APPROVED)
                ->where('can_transport_parcels', true))
            ->whereHas('vehicle', function ($query) use ($parcelWeightKg, $requiredVehicleType) {
                $query->where('status', TransportVehicle::STATUS_APPROVED)
                    ->where('can_carry_parcels', true)
                    ->where('accepts_payfast', true);

                if ($parcelWeightKg !== null && $parcelWeightKg !== '') {
                    $query->where(function ($weightQuery) use ($parcelWeightKg) {
                        $weightQuery->whereNull('max_weight_kg')
                            ->orWhere('max_weight_kg', '>=', (float) $parcelWeightKg);
                    });
                }

                if ($requiredVehicleType) {
                    $query->where('vehicle_type', $requiredVehicleType);
                }
            })
            ->limit(20)
            ->get();
    }

    private function pricingVehiclePayload(TransportDutySession $session): ?array
    {
        $vehicle = $session->vehicle;

        if (! $vehicle) {
            return null;
        }

        return [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'vehicle_type' => $vehicle->vehicle_type,
            'max_weight_kg' => $vehicle->max_weight_kg !== null ? (float) $vehicle->max_weight_kg : null,
            'base_fee' => (float) $vehicle->base_fee,
            'per_km_fee' => (float) $vehicle->per_km_fee,
            'minimum_fee' => (float) $vehicle->minimum_fee,
        ];
    }

    private function linkFulfillment(MallFulfillment $fulfillment, TransportRequest $request, Collection $offers): void
    {
        $meta = $fulfillment->meta ?? [];
        $meta['transport_request_id'] = $request->id;
        $meta['transport_offer_count'] = $offers->count();

        $fulfillment->update([
            'status' => $request->status,
            'external_type' => 'transport_request',
            'external_id' => $request->id,
            'meta' => $meta,
        ]);
    }

    private function nextRequestNumber(): string
    {
        do {
            $number = 'TRN-MALL-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (TransportRequest::where('request_number', $number)->exists());

        return $number;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
