<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportVehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverDutyController extends Controller
{
    public function show(Request $request): View
    {
        $driver = $request->user()->transportDriver()
            ->with(['vehicles', 'activeDutySession.vehicle'])
            ->first();

        return view('transport.driver.duty', [
            'driver' => $driver,
            'approvedVehicles' => $driver?->vehicles()
                ->where('status', TransportVehicle::STATUS_APPROVED)
                ->orderBy('name')
                ->get() ?? collect(),
            'activeSession' => $driver?->activeDutySession,
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $driver = $request->user()->transportDriver()->with('activeDutySession')->firstOrFail();

        abort_unless($driver->isApproved(), 403);

        $data = $request->validate([
            'transport_vehicle_id' => ['required', 'exists:transport_vehicles,id'],
        ]);

        if ($driver->activeDutySession) {
            return redirect()->route('transport.driver.workspace')
                ->with('status', 'You are already on duty.');
        }

        $vehicle = $driver->vehicles()
            ->where('id', $data['transport_vehicle_id'])
            ->where('status', TransportVehicle::STATUS_APPROVED)
            ->firstOrFail();

        $driver->dutySessions()->create([
            'transport_vehicle_id' => $vehicle->id,
            'status' => TransportDutySession::STATUS_AVAILABLE,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        return redirect()->route('transport.driver.workspace')
            ->with('status', 'You are on duty and available for transport requests.');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $session = $request->user()->transportDriver?->activeDutySession;

        if ($session) {
            $session->update([
                'status' => TransportDutySession::STATUS_ENDED,
                'ended_at' => now(),
            ]);
        }

        return redirect()->route('transport.driver.duty')
            ->with('status', 'You are now off duty.');
    }
}
