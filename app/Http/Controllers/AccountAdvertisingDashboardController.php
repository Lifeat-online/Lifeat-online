<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountAdvertisingDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $listings = Listing::with(['activeSubscription.package'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return view('account.advertising.dashboard', [
            'listings' => $listings,
        ]);
    }
}

