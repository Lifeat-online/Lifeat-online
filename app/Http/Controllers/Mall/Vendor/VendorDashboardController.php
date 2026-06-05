<?php

namespace App\Http\Controllers\Mall\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class VendorDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->user()->mallStore;

        $stats = \App\Support\Caching\PublicReadCache::vendorDashboardStats((int) $store->id);

        $recentOrders = $store->orders()->with('user', 'items')->latest()->limit(10)->get();

        return view('mall.vendor.dashboard', compact('store', 'stats', 'recentOrders'));
    }
}
