<?php

namespace App\Http\Controllers\Mall\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mall\Vendor\ProductCategoryRequest;
use App\Models\MallProductCategory;
use App\Models\MallStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorProductCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->currentStore($request);
        $categories = $store->productCategories()
            ->withCount('products')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('mall.vendor.product-categories.index', compact('store', 'categories'));
    }

    public function create(Request $request): View
    {
        $store = $this->currentStore($request);
        $category = new MallProductCategory(['sort_order' => 0]);

        return view('mall.vendor.product-categories.create', compact('store', 'category'));
    }

    public function store(ProductCategoryRequest $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $validated = $request->validated();

        $store->productCategories()->create([
            'name' => $validated['name'],
            'slug' => $this->uniqueCategorySlug($store->id, $validated['name']),
            'sort_order' => $validated['sort_order'],
        ]);

        return redirect()->route('mall.vendor.product-categories.index')->with('status', 'Product category created.');
    }

    public function edit(Request $request, MallProductCategory $productCategory): View
    {
        $store = $this->currentStore($request);
        $productCategory->loadCount('products');

        return view('mall.vendor.product-categories.edit', [
            'store' => $store,
            'category' => $productCategory,
        ]);
    }

    public function update(ProductCategoryRequest $request, MallProductCategory $productCategory): RedirectResponse
    {
        $store = $this->currentStore($request);
        $validated = $request->validated();
        $payload = [
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
        ];

        if ($productCategory->name !== $validated['name']) {
            $payload['slug'] = $this->uniqueCategorySlug($store->id, $validated['name'], $productCategory->id);
        }

        $productCategory->update($payload);

        return redirect()->route('mall.vendor.product-categories.index')->with('status', 'Product category updated.');
    }

    public function destroy(MallProductCategory $productCategory): RedirectResponse
    {
        if ($productCategory->products()->exists()) {
            return back()->with('status', 'Product categories attached to products cannot be deleted yet.');
        }

        $productCategory->delete();

        return redirect()->route('mall.vendor.product-categories.index')->with('status', 'Product category deleted.');
    }

    private function currentStore(Request $request): MallStore
    {
        return $request->attributes->get('mall_store') ?? $request->user()->mallStore;
    }

    private function uniqueCategorySlug(int $storeId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $index = 2;

        while (MallProductCategory::query()
            ->where('mall_store_id', $storeId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }
}
