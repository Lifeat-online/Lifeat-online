<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DriverWorkspaceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $driver = $request->user()->transportDriver()
            ->with(['activeDutySession.vehicle'])
            ->firstOrFail();

        return view('transport.driver.workspace', [
            'driver' => $driver,
            'activeSession' => $driver->activeDutySession,
            'activeRequests' => TransportRequest::with(['user', 'acceptedVehicle'])
                ->where('accepted_transport_driver_id', $driver->id)
                ->whereIn('status', [
                    TransportRequest::STATUS_ACCEPTED,
                    TransportRequest::STATUS_DRIVER_ARRIVING,
                    TransportRequest::STATUS_IN_TRANSIT,
                ])
                ->latest()
                ->get(),
            'offers' => $driver->requestOffers()
                ->with(['request.user', 'vehicle'])
                ->where('status', 'offered')
                ->whereHas('request', fn ($query) => $query->where('status', 'dispatching'))
                ->latest()
                ->get(),
        ]);
    }
}
