<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StaffAdvertisingDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $businesses = Listing::query()
            ->with(['owner', 'activeSubscription.package'])
            ->when(! $user->hasRole('admin'), fn ($query) => $query->where('registered_by_user_id', $user->id))
            ->orderBy('title')
            ->get();

        return view('staff.advertising.dashboard', [
            'businesses' => $businesses,
        ]);
    }
}
