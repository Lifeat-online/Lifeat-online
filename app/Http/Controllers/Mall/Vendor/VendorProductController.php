<?php

namespace App\Http\Controllers\Mall\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MallProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class VendorProductController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->user()->mallStore;
        $products = $store->products()->latest()->paginate(20);

        return view('mall.vendor.products.index', compact('store', 'products'));
    }

    public function create(Request $request): View
    {
        $store = $request->user()->mallStore;
        $categories = $store->productCategories()->orderBy('sort_order')->orderBy('name')->get();

        return view('mall.vendor.products.form', compact('store', 'categories') + ['product' => new MallProduct()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $request->user()->mallStore;
        $validated = $this->validateProduct($request);
        $images = $this->storeImages($request);

        $product = $store->products()->create(array_merge(Arr::except($validated, ['category_ids', 'images']), [
            'slug' => $this->uniqueProductSlug($store->id, $validated['name']),
            'images' => $images,
        ]));

        $product->categories()->sync($this->storeCategoryIds($store, $validated['category_ids'] ?? []));

        return redirect()->route('mall.vendor.products.index')->with('status', 'Product created.');
    }

    public function edit(Request $request, MallProduct $product): View
    {
        $store = $request->user()->mallStore;
        abort_unless($product->mall_store_id === $store->id, 404);

        $categories = $store->productCategories()->orderBy('sort_order')->orderBy('name')->get();
        $product->load('categories');

        return view('mall.vendor.products.form', compact('store', 'product', 'categories'));
    }

    public function update(Request $request, MallProduct $product): RedirectResponse
    {
        $store = $request->user()->mallStore;
        abort_unless($product->mall_store_id === $store->id, 404);

        $validated = $this->validateProduct($request);
        $images = $this->storeImages($request);
        $payload = Arr::except($validated, ['category_ids', 'images']);

        if ($product->name !== $validated['name']) {
            $payload['slug'] = $this->uniqueProductSlug($store->id, $validated['name'], $product->id);
        }

        if (! empty($images)) {
            $payload['images'] = array_values(array_merge($product->images ?? [], $images));
        }

        $product->update($payload);
        $product->categories()->sync($this->storeCategoryIds($store, $validated['category_ids'] ?? []));

        return redirect()->route('mall.vendor.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Request $request, MallProduct $product): RedirectResponse
    {
        $store = $request->user()->mallStore;
        abort_unless($product->mall_store_id === $store->id, 404);

        $product->delete();

        return redirect()->route('mall.vendor.products.index')->with('status', 'Product deleted.');
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'decimal:0,2', 'min:0'],
            'compare_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100'],
            'stock_qty' => ['required', 'integer', 'min:0'],
            'parcel_weight_kg' => ['nullable', 'decimal:0,3', 'min:0.001', 'max:999999'],
            'manage_stock' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:mall_product_categories,id'],
            'images' => ['array'],
            'images.*' => ['image', 'max:4096'],
        ]);
    }

    private function storeImages(Request $request): array
    {
        $images = [];

        foreach ($request->file('images', []) as $image) {
            $images[] = $image->store('mall/products', 'public');
        }

        return $images;
    }

    private function storeCategoryIds($store, array $categoryIds): array
    {
        return $store->productCategories()
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->all();
    }

    private function uniqueProductSlug(int $storeId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $index = 2;

        while (MallProduct::where('mall_store_id', $storeId)->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }
}
