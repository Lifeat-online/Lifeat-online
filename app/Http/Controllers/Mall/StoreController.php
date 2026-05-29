<?php

namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\MallProduct;
use App\Models\MallProductCategory;
use App\Models\MallStore;
use App\Services\MallCartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function window(MallStore $store): View
    {
        abort_unless($store->status === 'active', 404);

        $store->load('categories');
        $featuredProducts = $store->getFeaturedProducts();

        return view('mall.window', compact('store', 'featuredProducts'));
    }

    public function index(Request $request, MallStore $store, MallCartService $cartService): View
    {
        abort_unless($store->status === 'active', 404);

        $categories = MallProductCategory::query()
            ->where('mall_store_id', $store->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $products = $store->products()
            ->active()
            ->with('categories')
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category')));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('name', 'like', $term)
                        ->orWhere('short_description', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($request->string('sort')->toString() === 'price_asc', fn ($query) => $query->orderBy('price'))
            ->when($request->string('sort')->toString() === 'price_desc', fn ($query) => $query->orderByDesc('price'))
            ->when(! in_array($request->string('sort')->toString(), ['price_asc', 'price_desc'], true), fn ($query) => $query->orderByDesc('is_featured')->orderBy('name'))
            ->paginate(16)
            ->withQueryString();

        $cart = $cartService->getCart($store);

        return view('mall.store.index', compact('store', 'categories', 'products', 'cart'));
    }

    public function product(MallStore $store, MallProduct $product, MallCartService $cartService): View
    {
        abort_unless($store->status === 'active', 404);
        abort_unless($product->mall_store_id === $store->id && $product->is_active, 404);

        $cart = $cartService->getCart($store);
        $product->load('categories');

        return view('mall.store.product', compact('store', 'product', 'cart'));
    }
}
