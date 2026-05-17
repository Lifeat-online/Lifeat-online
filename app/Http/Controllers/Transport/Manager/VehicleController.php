<?php

namespace App\Http\Controllers\Transport\Manager;

use App\Http\Controllers\Controller;
use App\Models\TransportDriver;
use App\Models\TransportVehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transport_driver_id' => ['nullable', 'exists:transport_drivers,id'],
            'name' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['required', 'in:bicycle,scooter,motorcycle,car,bakkie,ldv,van,truck,trailer'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:pending,approved,suspended'],
            'can_carry_people' => ['nullable', 'boolean'],
            'can_carry_parcels' => ['nullable', 'boolean'],
            'max_passengers' => ['nullable', 'integer', 'min:0', 'max:80'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'pricing_mode' => ['required', 'in:per_km,per_km_plus_people'],
            'base_fee' => ['required', 'numeric', 'min:0'],
            'per_km_fee' => ['required', 'numeric', 'min:0'],
            'per_person_fee' => ['nullable', 'numeric', 'min:0'],
            'minimum_fee' => ['required', 'numeric', 'min:0'],
            'waiting_fee' => ['nullable', 'numeric', 'min:0'],
            'cancellation_fee' => ['nullable', 'numeric', 'min:0'],
            'accepts_cash' => ['nullable', 'boolean'],
            'has_card_machine' => ['nullable', 'boolean'],
            'accepts_payfast' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $driver = isset($data['transport_driver_id'])
            ? TransportDriver::find($data['transport_driver_id'])
            : null;

        TransportVehicle::create([
            'transport_driver_id' => $driver?->id,
            'manager_user_id' => $request->user()->id,
            'name' => $data['name'],
            'vehicle_type' => $data['vehicle_type'],
            'registration_number' => $data['registration_number'] ?? null,
            'status' => $data['status'],
            'can_carry_people' => $request->boolean('can_carry_people'),
            'can_carry_parcels' => $request->boolean('can_carry_parcels', true),
            'max_passengers' => $data['max_passengers'] ?? 0,
            'max_weight_kg' => $data['max_weight_kg'] ?? null,
            'pricing_mode' => $data['pricing_mode'],
            'base_fee' => $data['base_fee'],
            'per_km_fee' => $data['per_km_fee'],
            'per_person_fee' => $data['per_person_fee'] ?? 0,
            'minimum_fee' => $data['minimum_fee'],
            'waiting_fee' => $data['waiting_fee'] ?? 0,
            'cancellation_fee' => $data['cancellation_fee'] ?? 0,
            'accepts_cash' => $request->boolean('accepts_cash', true),
            'has_card_machine' => $request->boolean('has_card_machine'),
            'accepts_payfast' => $request->boolean('accepts_payfast', true),
            'approved_at' => $data['status'] === TransportVehicle::STATUS_APPROVED ? now() : null,
            'approved_by_user_id' => $data['status'] === TransportVehicle::STATUS_APPROVED ? $request->user()->id : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('transport.manager.dashboard')
            ->with('status', 'Vehicle saved.');
    }
}
