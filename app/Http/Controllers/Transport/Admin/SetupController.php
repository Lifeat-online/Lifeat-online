<?php

namespace App\Http\Controllers\Transport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use App\Models\TransportRequestOffer;
use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SetupController extends Controller
{
    public function index(): View
    {
        return view('transport.admin.setup', [
            'counts' => [
                'managers' => $this->managerUsersQuery()->count(),
                'drivers' => TransportDriver::count(),
                'approvedDrivers' => TransportDriver::where('status', TransportDriver::STATUS_APPROVED)->count(),
                'vehicles' => TransportVehicle::count(),
                'approvedVehicles' => TransportVehicle::where('status', TransportVehicle::STATUS_APPROVED)->count(),
                'activeDuty' => TransportDutySession::whereNull('ended_at')->count(),
                'openRequests' => TransportRequest::whereIn('status', [
                    TransportRequest::STATUS_DISPATCHING,
                    TransportRequest::STATUS_SCHEDULED,
                    TransportRequest::STATUS_ACCEPTED,
                    TransportRequest::STATUS_DRIVER_ARRIVING,
                    TransportRequest::STATUS_IN_TRANSIT,
                ])->count(),
            ],
            'settings' => $this->settings(),
            'managers' => $this->managerUsersQuery()
                ->latest()
                ->limit(12)
                ->get(),
            'recentDrivers' => TransportDriver::with(['user', 'manager', 'vehicles'])
                ->latest()
                ->limit(8)
                ->get(),
            'recentVehicles' => TransportVehicle::with(['driver.user', 'manager'])
                ->latest()
                ->limit(8)
                ->get(),
            'recentRequests' => TransportRequest::with(['user', 'acceptedDriver.user', 'acceptedVehicle'])
                ->latest()
                ->limit(8)
                ->get(),
            'recentOffers' => TransportRequestOffer::with(['transportRequest', 'driver.user', 'vehicle'])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function storeManager(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $password = null;

        if ($user) {
            $user->fill([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? $user->phone,
            ])->save();
        } else {
            $password = Str::password(14);
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($password),
                'role' => 'transport_manager',
            ]);
        }

        $role = Role::firstOrCreate(
            ['slug' => 'transport_manager'],
            ['name' => 'Transport Manager'],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        $redirect = redirect()
            ->route('dev.transport.setup')
            ->with('status', $password ? 'Transport manager created.' : 'Transport manager access granted.')
            ->with('manager_email', $user->email);

        if ($password) {
            $redirect->with('temporary_password', $password);
        }

        return $redirect;
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'dispatch_offer_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'default_search_radius_km' => ['required', 'numeric', 'min:1', 'max:500'],
            'safety_contact_phone' => ['nullable', 'string', 'max:255'],
            'safety_contact_email' => ['nullable', 'email', 'max:255'],
            'panic_button_mode' => ['required', Rule::in(['manual_contact', 'support_dispatch', 'emergency_services'])],
            'require_driver_id_number' => ['nullable', 'boolean'],
            'require_driver_license' => ['nullable', 'boolean'],
            'cash_enabled' => ['nullable', 'boolean'],
            'card_machine_enabled' => ['nullable', 'boolean'],
            'payfast_enabled' => ['nullable', 'boolean'],
        ]);

        foreach ($this->settingDefinitions() as $field => $definition) {
            $value = $validated[$field] ?? '0';

            Setting::updateOrCreate(
                ['key' => $definition['key']],
                [
                    'value' => (string) $value,
                    'type' => $definition['type'],
                    'group' => 'transport',
                    'updated_by_user_id' => $request->user()?->id,
                ],
            );
        }

        return redirect()
            ->route('dev.transport.setup')
            ->with('status', 'Transport setup updated.');
    }

    private function settings(): array
    {
        return collect($this->settingDefinitions())
            ->mapWithKeys(fn (array $definition, string $field) => [
                $field => Setting::getValue($definition['key'], $definition['default']),
            ])
            ->all();
    }

    private function managerUsersQuery()
    {
        return User::query()
            ->where('role', 'transport_manager')
            ->orWhereHas('roles', fn ($query) => $query->where('slug', 'transport_manager'));
    }

    private function settingDefinitions(): array
    {
        return [
            'platform_fee_percent' => ['key' => 'transport.platform_fee_percent', 'type' => 'decimal', 'default' => '10'],
            'dispatch_offer_limit' => ['key' => 'transport.dispatch_offer_limit', 'type' => 'integer', 'default' => '20'],
            'default_search_radius_km' => ['key' => 'transport.default_search_radius_km', 'type' => 'decimal', 'default' => '25'],
            'safety_contact_phone' => ['key' => 'transport.safety_contact_phone', 'type' => 'string', 'default' => ''],
            'safety_contact_email' => ['key' => 'transport.safety_contact_email', 'type' => 'string', 'default' => ''],
            'panic_button_mode' => ['key' => 'transport.panic_button_mode', 'type' => 'string', 'default' => 'manual_contact'],
            'require_driver_id_number' => ['key' => 'transport.require_driver_id_number', 'type' => 'integer', 'default' => '1'],
            'require_driver_license' => ['key' => 'transport.require_driver_license', 'type' => 'integer', 'default' => '1'],
            'cash_enabled' => ['key' => 'transport.cash_enabled', 'type' => 'integer', 'default' => '1'],
            'card_machine_enabled' => ['key' => 'transport.card_machine_enabled', 'type' => 'integer', 'default' => '1'],
            'payfast_enabled' => ['key' => 'transport.payfast_enabled', 'type' => 'integer', 'default' => '1'],
        ];
    }
}
