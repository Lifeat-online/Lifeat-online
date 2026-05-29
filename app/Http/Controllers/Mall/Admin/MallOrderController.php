<?php

namespace App\Http\Controllers\Mall\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MallOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = MallOrder::query()
            ->with('store', 'user')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('mall.admin.orders.index', compact('orders'));
    }

    public function show(MallOrder $order): View
    {
        $order->load('store', 'user', 'items.product', 'payments', 'fulfillment');

        return view('mall.admin.orders.show', compact('order'));
    }
}
