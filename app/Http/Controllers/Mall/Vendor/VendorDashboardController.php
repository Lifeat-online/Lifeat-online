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
        $store->load('products', 'orders.items');

        $stats = [
            'total_orders' => $store->orders()->count(),
            'paid_orders' => $store->orders()->where('status', 'paid')->count(),
            'total_revenue' => $store->orders()->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])->sum('vendor_amount'),
            'total_products' => $store->products()->count(),
            'low_stock' => $store->products()->where('manage_stock', true)->where('stock_qty', '<=', 5)->count(),
        ];

        $recentOrders = $store->orders()->with('user', 'items')->latest()->limit(10)->get();

        return view('mall.vendor.dashboard', compact('store', 'stats', 'recentOrders'));
    }
}
