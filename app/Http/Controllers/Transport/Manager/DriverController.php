<?php

namespace App\Http\Controllers\Transport\Manager;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TransportDriver;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index(): View
    {
        return view('transport.manager.drivers.index', [
            'drivers' => TransportDriver::with(['user', 'vehicles', 'activeDutySession.vehicle'])
                ->latest()
                ->paginate(50),
        ]);
    }

    public function edit(TransportDriver $driver): View
    {
        return view('transport.manager.drivers.edit', [
            'driver' => $driver->load(['user', 'vehicles', 'activeDutySession.vehicle']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'id_number' => ['nullable', 'string', 'max:80'],
            'license_number' => ['nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'can_transport_people' => ['nullable', 'boolean'],
            'can_transport_parcels' => ['nullable', 'boolean'],
            'status' => ['required', 'in:pending,approved,suspended'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make(Str::random(32)),
                'role' => 'transport_driver',
                'phone' => $data['phone'] ?? null,
            ],
        );

        $user->fill([
            'name' => $data['name'],
            'role' => 'transport_driver',
            'phone' => $data['phone'] ?? $user->phone,
        ])->save();

        $this->syncDriverRole($user);

        $driver = TransportDriver::updateOrCreate(
            ['user_id' => $user->id],
            [
                'manager_user_id' => $request->user()->id,
                'status' => $data['status'],
                'phone' => $data['phone'] ?? null,
                'id_number' => $data['id_number'] ?? null,
                'license_number' => $data['license_number'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'can_transport_people' => $request->boolean('can_transport_people'),
                'can_transport_parcels' => $request->boolean('can_transport_parcels', true),
                'notes' => $data['notes'] ?? null,
                'approved_at' => $data['status'] === TransportDriver::STATUS_APPROVED ? now() : null,
                'approved_by_user_id' => $data['status'] === TransportDriver::STATUS_APPROVED ? $request->user()->id : null,
            ],
        );

        return redirect()->route('transport.manager.dashboard')
            ->with('status', "Driver profile saved for {$driver->user->name}.");
    }

    public function update(Request $request, TransportDriver $driver): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($driver->user_id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'id_number' => ['nullable', 'string', 'max:80'],
            'license_number' => ['nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'can_transport_people' => ['nullable', 'boolean'],
            'can_transport_parcels' => ['nullable', 'boolean'],
            'status' => ['required', 'in:pending,approved,suspended'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $driver->loadMissing('user');

        $driver->user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ])->save();

        $this->syncDriverRole($driver->user);

        $isApproved = $data['status'] === TransportDriver::STATUS_APPROVED;

        $driver->fill([
            'manager_user_id' => $request->user()->id,
            'status' => $data['status'],
            'phone' => $data['phone'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'license_number' => $data['license_number'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'can_transport_people' => $request->boolean('can_transport_people'),
            'can_transport_parcels' => $request->boolean('can_transport_parcels'),
            'notes' => $data['notes'] ?? null,
            'approved_at' => $isApproved ? ($driver->approved_at ?? now()) : null,
            'approved_by_user_id' => $isApproved ? ($driver->approved_by_user_id ?? $request->user()->id) : null,
        ])->save();

        return redirect()->route('transport.manager.drivers.index')
            ->with('status', "Driver profile updated for {$driver->user->name}.");
    }

    private function syncDriverRole(User $user): void
    {
        if ($role = Role::where('slug', 'transport_driver')->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
