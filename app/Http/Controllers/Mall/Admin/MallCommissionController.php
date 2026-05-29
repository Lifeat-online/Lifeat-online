<?php

namespace App\Http\Controllers\Mall\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use Illuminate\Contracts\View\View;

class MallCommissionController extends Controller
{
    public function index(): View
    {
        $paidStatuses = ['paid', 'processing', 'shipped', 'completed'];
        $totals = [
            'gross' => MallOrder::whereIn('status', $paidStatuses)->sum('total'),
            'platform_fee' => MallOrder::whereIn('status', $paidStatuses)->sum('platform_fee'),
            'vendor_amount' => MallOrder::whereIn('status', $paidStatuses)->sum('vendor_amount'),
            'orders' => MallOrder::whereIn('status', $paidStatuses)->count(),
        ];

        $stores = MallOrder::query()
            ->selectRaw('mall_store_id, COUNT(*) as orders_count, SUM(total) as gross_total, SUM(platform_fee) as platform_total, SUM(vendor_amount) as vendor_total')
            ->with('store')
            ->whereIn('status', $paidStatuses)
            ->groupBy('mall_store_id')
            ->orderByDesc('gross_total')
            ->get();

        return view('mall.admin.commissions.index', compact('totals', 'stores'));
    }
}
