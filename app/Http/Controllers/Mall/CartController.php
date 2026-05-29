<?php

namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\MallCartItem;
use App\Models\MallProduct;
use App\Models\MallStore;
use App\Services\MallCartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private MallCartService $cartService) {}

    public function show(MallStore $store): View
    {
        abort_unless($store->status === 'active', 404);

        $cart = $this->cartService->getCart($store);

        return view('mall.cart', compact('store', 'cart'));
    }

    public function store(MallStore $store, MallProduct $product, Request $request): RedirectResponse
    {
        abort_unless($store->status === 'active', 404);

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $this->cartService->addProduct($store, $product, (int) ($validated['quantity'] ?? 1));

        return back()->with('status', 'Added to your basket at '.$store->name.'.');
    }

    public function update(MallStore $store, MallCartItem $item, Request $request): RedirectResponse
    {
        $this->authorizeCartItem($store, $item);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->cartService->updateQuantity($item, (int) $validated['quantity']);

        return back()->with('status', 'Basket updated.');
    }

    public function destroy(MallStore $store, MallCartItem $item): RedirectResponse
    {
        $this->authorizeCartItem($store, $item);
        $this->cartService->removeItem($item);

        return back()->with('status', 'Item removed.');
    }

    private function authorizeCartItem(MallStore $store, MallCartItem $item): void
    {
        $cart = $this->cartService->getCart($store);

        abort_unless($item->mall_cart_id === $cart->id, 404);
    }
}
