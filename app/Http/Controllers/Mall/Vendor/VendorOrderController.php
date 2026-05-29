<?php

namespace App\Http\Controllers\Mall\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MallOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorOrderController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->user()->mallStore;
        $orders = $store->orders()->with('user', 'items', 'fulfillment')->latest()->paginate(20);

        return view('mall.vendor.orders.index', compact('store', 'orders'));
    }

    public function show(Request $request, MallOrder $order): View
    {
        $store = $request->user()->mallStore;
        abort_unless($order->mall_store_id === $store->id, 404);

        $order->load('user', 'items.product', 'payments', 'fulfillment');

        return view('mall.vendor.orders.show', compact('store', 'order'));
    }

    public function updateStatus(Request $request, MallOrder $order): RedirectResponse
    {
        $store = $request->user()->mallStore;
        abort_unless($order->mall_store_id === $store->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['paid', 'processing', 'shipped', 'completed', 'cancelled'])],
        ]);

        if ($validated['status'] === 'cancelled' && $order->status !== 'cancelled') {
            $order->loadMissing('items.product');

            foreach ($order->items as $item) {
                if ($item->product?->manage_stock && in_array($order->status, ['paid', 'processing'], true)) {
                    $item->product->increment('stock_qty', $item->quantity);
                }
            }
        }

        $order->update(['status' => $validated['status']]);

        return back()->with('status', 'Order status updated.');
    }
}
