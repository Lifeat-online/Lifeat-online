<?php

namespace App\Http\Controllers\Mall\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mall\Admin\ProductCategoryRequest;
use App\Models\MallProductCategory;
use App\Models\MallStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MallProductCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = MallProductCategory::query()
            ->with('store')
            ->withCount('products')
            ->when($request->filled('store_id'), fn ($query) => $query->where('mall_store_id', $request->integer('store_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
            })
            ->orderBy('mall_store_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $stores = $this->stores();

        return view('mall.admin.product-categories.index', compact('categories', 'stores'));
    }

    public function create(Request $request): View
    {
        $category = new MallProductCategory([
            'mall_store_id' => $request->integer('store_id') ?: null,
            'sort_order' => 0,
        ]);
        $stores = $this->stores();

        return view('mall.admin.product-categories.create', compact('category', 'stores'));
    }

    public function store(ProductCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MallProductCategory::create([
            'mall_store_id' => $validated['mall_store_id'],
            'name' => $validated['name'],
            'slug' => $this->uniqueCategorySlug((int) $validated['mall_store_id'], $validated['name']),
            'sort_order' => $validated['sort_order'],
        ]);

        return redirect()->route('mall.admin.product-categories.index')->with('status', 'Product category created.');
    }

    public function edit(MallProductCategory $productCategory): View
    {
        $productCategory->load('store')->loadCount('products');
        $stores = $this->stores();

        return view('mall.admin.product-categories.edit', [
            'category' => $productCategory,
            'stores' => $stores,
        ]);
    }

    public function update(ProductCategoryRequest $request, MallProductCategory $productCategory): RedirectResponse
    {
        $validated = $request->validated();
        $storeId = (int) $validated['mall_store_id'];

        if ((int) $productCategory->mall_store_id !== $storeId && $productCategory->products()->exists()) {
            return back()
                ->withErrors(['mall_store_id' => 'Move products out of this category before assigning it to another store.'])
                ->withInput();
        }

        $payload = [
            'mall_store_id' => $storeId,
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
        ];

        if ((int) $productCategory->mall_store_id !== $storeId || $productCategory->name !== $validated['name']) {
            $payload['slug'] = $this->uniqueCategorySlug($storeId, $validated['name'], $productCategory->id);
        }

        $productCategory->update($payload);

        return redirect()->route('mall.admin.product-categories.index')->with('status', 'Product category updated.');
    }

    public function destroy(MallProductCategory $productCategory): RedirectResponse
    {
        if ($productCategory->products()->exists()) {
            return back()->with('status', 'Product categories attached to products cannot be deleted yet.');
        }

        $productCategory->delete();

        return redirect()->route('mall.admin.product-categories.index')->with('status', 'Product category deleted.');
    }

    private function stores()
    {
        return MallStore::query()->orderBy('name')->get(['id', 'name']);
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
