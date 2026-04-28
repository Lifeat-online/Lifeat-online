<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class AdvertiseController extends Controller
{
    public function index(): View
    {
        $directoryPackages = Package::with(['type', 'prices'])
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', 'business_directory'))
            ->orderBy('is_self_service')
            ->get();

        $eventPackages = Package::with(['type', 'prices'])
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', 'event_package'))
            ->orderBy('name')
            ->get();

        return view('advertise.index', [
            'directoryPackages' => $directoryPackages,
            'eventPackages' => $eventPackages,
            'pricing' => [
                'directory_standard_6m' => (float) Setting::getValue('pricing.business_directory_6m', 500),
                'directory_self_service_6m' => (float) Setting::getValue('pricing.business_directory_self_service_6m', 750),
                'event_one_off' => (float) Setting::getValue('pricing.event_one_off', 250),
                'event_monthly' => (float) Setting::getValue('pricing.event_monthly', 99),
                'push_notification' => (float) Setting::getValue('pricing.push_notification', 0),
            ],
        ]);
    }
}
