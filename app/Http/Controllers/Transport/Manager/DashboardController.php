<?php

namespace App\Http\Controllers\Transport\Manager;

use App\Http\Controllers\Controller;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportVehicle;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('transport.manager.dashboard', [
            'counts' => [
                'drivers' => TransportDriver::count(),
                'approvedDrivers' => TransportDriver::where('status', TransportDriver::STATUS_APPROVED)->count(),
                'vehicles' => TransportVehicle::count(),
                'activeDuty' => TransportDutySession::whereNull('ended_at')->count(),
            ],
            'drivers' => TransportDriver::with(['user', 'vehicles', 'activeDutySession.vehicle'])->latest()->limit(8)->get(),
            'driverOptions' => TransportDriver::with('user')->orderByDesc('id')->get(),
            'vehicles' => TransportVehicle::with(['driver.user'])->latest()->limit(8)->get(),
        ]);
    }
}
