<?php

namespace App\Http\Controllers\Transport\Manager;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TransportDriver;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DriverController extends Controller
{
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

        if ($role = Role::where('slug', 'transport_driver')->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

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
}
