<?php

namespace App\Http\Controllers\Mall\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallProduct;
use App\Models\MallStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MallProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = MallProduct::query()
            ->with('store', 'categories')
            ->when($request->filled('store_id'), fn ($query) => $query->where('mall_store_id', $request->integer('store_id')))
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhere('short_description', 'like', $term);
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stores = MallStore::query()->orderBy('name')->get(['id', 'name']);

        return view('mall.admin.products.index', compact('products', 'stores'));
    }

    public function edit(MallProduct $product): View
    {
        $product->load('store.productCategories', 'categories');
        $categories = $product->store->productCategories()->orderBy('sort_order')->orderBy('name')->get();

        return view('mall.admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, MallProduct $product): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $payload = Arr::except($validated, ['category_ids']);

        if ($product->name !== $validated['name']) {
            $payload['slug'] = $this->uniqueProductSlug($product->mall_store_id, $validated['name'], $product->id);
        }

        $product->update($payload);
        $allowedCategoryIds = $product->store->productCategories()
            ->whereIn('id', $validated['category_ids'] ?? [])
            ->pluck('id')
            ->all();
        $product->categories()->sync($allowedCategoryIds);

        return redirect()->route('mall.admin.products.index')->with('status', 'Mall product updated.');
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
        ]);
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
