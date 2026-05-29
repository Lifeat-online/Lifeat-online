<?php

namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()
            ->mallOrders()
            ->with('store', 'fulfillment')
            ->latest()
            ->paginate(20);

        return view('mall.account.orders.index', compact('orders'));
    }

    public function show(Request $request, MallOrder $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load('store', 'items.product', 'payments', 'fulfillment');

        return view('mall.account.orders.show', compact('order'));
    }
}
