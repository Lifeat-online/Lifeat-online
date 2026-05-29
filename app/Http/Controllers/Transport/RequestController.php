<?php

namespace App\Http\Controllers\Transport;

use App\Events\TransportRequestStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TransportRequest;
use App\Services\TransportDispatchService;
use App\Models\TransportDutySession;
use App\Models\TransportDriver;
use App\Models\TransportVehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestController extends Controller
{
    public function create(): View
    {
        $activeSessions = TransportDutySession::query()
            ->with(['driver', 'vehicle'])
            ->whereNull('ended_at')
            ->where('status', TransportDutySession::STATUS_AVAILABLE)
            ->whereHas('driver', fn ($query) => $query->where('status', TransportDriver::STATUS_APPROVED))
            ->whereHas('vehicle', fn ($query) => $query->where('status', TransportVehicle::STATUS_APPROVED))
            ->get();

        return view('transport.requests.create', [
            'activeDriverCount' => $activeSessions->count(),
            'platformFeePercent' => (float) Setting::getValue('transport.platform_fee_percent', 10),
            'pricingVehicles' => $activeSessions
                ->map(fn (TransportDutySession $session) => $this->pricingVehiclePayload($session))
                ->filter()
                ->values(),
        ]);
    }

    public function store(Request $request, TransportDispatchService $dispatchService): RedirectResponse
    {
        $data = $request->validate([
            'service_type' => ['required', 'in:ride,parcel,errand,heavy_goods'],
            'payment_method' => ['required', 'in:payfast,cash,card_machine'],
            'request_timing' => ['required', 'in:immediate,scheduled'],
            'scheduled_pickup_at' => ['required_if:request_timing,scheduled', 'nullable', 'date', 'after:now'],
            'pickup_address' => ['required', 'string', 'max:255'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['required', 'string', 'max:255'],
            'dropoff_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'distance_km' => ['required', 'numeric', 'min:0.1', 'max:2000'],
            'passenger_count' => ['nullable', 'integer', 'min:0', 'max:80'],
            'parcel_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'required_vehicle_type' => ['nullable', 'in:bicycle,scooter,motorcycle,car,bakkie,ldv,van,truck,trailer'],
            'client_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $isScheduled = $data['request_timing'] === 'scheduled';
        $isPassengerRide = $data['service_type'] === 'ride';
        $carriesParcelWeight = in_array($data['service_type'], ['parcel', 'heavy_goods'], true);

        $transportRequest = TransportRequest::create([
            'user_id' => $request->user()->id,
            'request_number' => $this->nextRequestNumber(),
            'service_type' => $data['service_type'],
            'status' => $isScheduled ? TransportRequest::STATUS_SCHEDULED : TransportRequest::STATUS_DISPATCHING,
            'payment_method' => $data['payment_method'],
            'request_timing' => $data['request_timing'],
            'scheduled_pickup_at' => $data['scheduled_pickup_at'] ?? null,
            'dispatch_started_at' => $isScheduled ? null : now(),
            'pickup_address' => $data['pickup_address'],
            'pickup_latitude' => $data['pickup_latitude'] ?? null,
            'pickup_longitude' => $data['pickup_longitude'] ?? null,
            'dropoff_address' => $data['dropoff_address'],
            'dropoff_latitude' => $data['dropoff_latitude'] ?? null,
            'dropoff_longitude' => $data['dropoff_longitude'] ?? null,
            'distance_km' => $data['distance_km'],
            'passenger_count' => $isPassengerRide ? ($data['passenger_count'] ?? 1) : 0,
            'parcel_weight_kg' => $carriesParcelWeight ? ($data['parcel_weight_kg'] ?? null) : null,
            'required_vehicle_type' => $data['required_vehicle_type'] ?? null,
            'client_notes' => $data['client_notes'] ?? null,
            'quoted_amount' => 0,
            'platform_fee' => 0,
            'driver_amount' => 0,
        ]);

        $offers = $isScheduled ? collect() : $dispatchService->createOffers($transportRequest);
        $bestOffer = $offers->sortBy('quoted_amount')->first();

        if ($bestOffer) {
            $transportRequest->update([
                'quoted_amount' => $bestOffer->quoted_amount,
                'platform_fee' => $bestOffer->platform_fee,
                'driver_amount' => $bestOffer->driver_amount,
            ]);
        }

        if (! $isScheduled && $offers->isEmpty()) {
            $transportRequest->update([
                'status' => TransportRequest::STATUS_SCHEDULED,
                'request_timing' => 'scheduled',
                'scheduled_pickup_at' => $transportRequest->scheduled_pickup_at ?? now()->addHour(),
            ]);
        }

        $status = $transportRequest->fresh()->status;

        $transportRequest->statusEvents()->create([
            'actor_user_id' => $request->user()->id,
            'status' => $status,
            'notes' => $this->statusNote($isScheduled, $offers->count()),
        ]);

        if ($offers->isNotEmpty()) {
            event(new TransportRequestStatusChanged($transportRequest->fresh(), 'Request dispatched to active drivers.'));
        }

        return redirect()->route('transport.requests.show', $transportRequest)
            ->with('status', $this->flashMessage($isScheduled, $offers->count()));
    }

    public function show(Request $request, TransportRequest $transportRequest): View
    {
        abort_unless($transportRequest->user_id === $request->user()->id || $request->user()->hasRole('transport_manager', 'admin', 'support', 'dev'), 403);

        $transportRequest->load([
            'acceptedDriver.user',
            'acceptedDriver.activeDutySession',
            'acceptedVehicle',
            'offers.driver.user',
            'offers.vehicle',
            'statusEvents.actor',
        ]);

        return view('transport.requests.show', [
            'transportRequest' => $transportRequest,
        ]);
    }

    private function nextRequestNumber(): string
    {
        return 'TRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function statusNote(bool $isScheduled, int $offerCount): string
    {
        if ($isScheduled) {
            return 'Scheduled request saved for later dispatch.';
        }

        if ($offerCount === 0) {
            return 'No eligible active drivers were available. Request held as scheduled for later dispatch.';
        }

        return $offerCount.' eligible driver offer(s) created.';
    }

    private function flashMessage(bool $isScheduled, int $offerCount): string
    {
        if ($isScheduled) {
            return 'Scheduled request saved. Drivers will be offered the job closer to pickup time.';
        }

        if ($offerCount === 0) {
            return 'No matching drivers are online right now. Your request was saved as scheduled so it can be dispatched later.';
        }

        return 'Request created and sent to available drivers.';
    }

    private function pricingVehiclePayload(TransportDutySession $session): ?array
    {
        $vehicle = $session->vehicle;
        $driver = $session->driver;

        if (! $vehicle || ! $driver) {
            return null;
        }

        return [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'vehicle_type' => $vehicle->vehicle_type,
            'driver_id' => $driver->id,
            'can_transport_people' => $driver->can_transport_people,
            'can_transport_parcels' => $driver->can_transport_parcels,
            'can_carry_people' => $vehicle->can_carry_people,
            'can_carry_parcels' => $vehicle->can_carry_parcels,
            'max_passengers' => (int) $vehicle->max_passengers,
            'max_weight_kg' => $vehicle->max_weight_kg !== null ? (float) $vehicle->max_weight_kg : null,
            'pricing_mode' => $vehicle->pricing_mode,
            'base_fee' => (float) $vehicle->base_fee,
            'per_km_fee' => (float) $vehicle->per_km_fee,
            'per_person_fee' => (float) $vehicle->per_person_fee,
            'minimum_fee' => (float) $vehicle->minimum_fee,
            'accepts_cash' => $vehicle->accepts_cash,
            'has_card_machine' => $vehicle->has_card_machine,
            'accepts_payfast' => $vehicle->accepts_payfast,
        ];
    }
}
