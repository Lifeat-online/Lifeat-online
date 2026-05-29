<?php

namespace App\Services;

use App\Models\MallCart;
use App\Models\MallCartItem;
use App\Models\MallProduct;
use App\Models\MallStore;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MallCartService
{
    public function getCart(MallStore $store): MallCart
    {
        if (auth()->check()) {
            return MallCart::findOrCreateForUser(auth()->id(), $store->id)->load('items.product');
        }

        return MallCart::findOrCreateForGuest($this->sessionToken(), $store->id)->load('items.product');
    }

    public function addProduct(MallStore $store, MallProduct $product, int $quantity = 1): MallCartItem
    {
        $quantity = max(1, $quantity);

        if ($product->mall_store_id !== $store->id || ! $product->is_active) {
            throw ValidationException::withMessages([
                'product' => 'This product is not available in this store.',
            ]);
        }

        if (! $product->isInStock($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'This product is out of stock.',
            ]);
        }

        $cart = $this->getCart($store);
        $item = $cart->items()->where('mall_product_id', $product->id)->first();

        if ($item) {
            $newQuantity = $item->quantity + $quantity;

            if (! $product->isInStock($newQuantity)) {
                throw ValidationException::withMessages([
                    'quantity' => 'There is not enough stock for that quantity.',
                ]);
            }

            $item->update(['quantity' => $newQuantity]);

            return $item->refresh();
        }

        return $cart->items()->create([
            'mall_product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
        ]);
    }

    public function updateQuantity(MallCartItem $item, int $quantity): MallCartItem
    {
        $quantity = max(1, $quantity);
        $item->loadMissing('product');

        if ($item->product && ! $item->product->isInStock($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'There is not enough stock for that quantity.',
            ]);
        }

        $item->update(['quantity' => $quantity]);

        return $item->refresh();
    }

    public function removeItem(MallCartItem $item): void
    {
        $item->delete();
    }

    private function sessionToken(): string
    {
        $key = config('mall.guest_cart_session_key', 'mall_cart_token');

        if (! session()->has($key)) {
            session()->put($key, Str::random(64));
        }

        return (string) session($key);
    }
}
