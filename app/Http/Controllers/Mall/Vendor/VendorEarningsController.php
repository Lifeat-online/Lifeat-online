<?php

namespace App\Http\Controllers\Mall\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class VendorEarningsController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->user()->mallStore;
        $orders = $store->orders()
            ->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])
            ->latest('paid_at')
            ->paginate(20);

        $totals = [
            'gross' => $store->orders()->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])->sum('total'),
            'platform_fee' => $store->orders()->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])->sum('platform_fee'),
            'vendor_amount' => $store->orders()->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])->sum('vendor_amount'),
        ];

        return view('mall.vendor.earnings.index', compact('store', 'orders', 'totals'));
    }
}
