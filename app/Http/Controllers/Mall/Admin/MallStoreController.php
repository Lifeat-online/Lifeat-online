<?php

namespace App\Http\Controllers\Mall\Admin;

use App\Http\Controllers\Controller;
use App\Models\MallStore;
use App\Models\MallStoreCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MallStoreController extends Controller
{
    public function index(Request $request): View
    {
        $stores = MallStore::query()
            ->with('owner', 'vendorProfile')
            ->withCount('products', 'orders')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('mall.admin.stores.index', compact('stores'));
    }

    public function show(MallStore $store): View
    {
        $store->load('owner', 'vendorProfile', 'categories', 'products', 'orders');

        return view('mall.admin.stores.show', compact('store'));
    }

    public function edit(MallStore $store): View
    {
        $store->load('categories', 'vendorProfile');
        $categories = MallStoreCategory::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('mall.admin.stores.edit', compact('store', 'categories'));
    }

    public function update(Request $request, MallStore $store): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pickup_address' => ['nullable', 'string', 'max:500'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-35,-22'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:16,33'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'closed'])],
            'is_featured' => ['sometimes', 'boolean'],
            'payfast_merchant_id' => ['nullable', 'string', 'max:20'],
            'payfast_merchant_key' => ['nullable', 'string', 'max:20'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:mall_store_categories,id'],
        ]);

        $store->update([
            'name' => $validated['name'],
            'tagline' => $validated['tagline'] ?? null,
            'description' => $validated['description'] ?? null,
            'pickup_address' => $validated['pickup_address'] ?? null,
            'pickup_latitude' => $validated['pickup_latitude'] ?? null,
            'pickup_longitude' => $validated['pickup_longitude'] ?? null,
            'primary_color' => $validated['primary_color'],
            'status' => $validated['status'],
            'is_featured' => $request->boolean('is_featured'),
            'payfast_merchant_id' => $validated['payfast_merchant_id'] ?? null,
            'payfast_merchant_key' => $validated['payfast_merchant_key'] ?? null,
        ]);
        $store->categories()->sync($validated['category_ids'] ?? []);

        if ($validated['status'] === 'active') {
            $store->vendorProfile?->update([
                'approved_at' => $store->vendorProfile?->approved_at ?? now(),
                'approved_by' => $store->vendorProfile?->approved_by ?? $request->user()->id,
            ]);
        }

        return redirect()->route('mall.admin.stores.show', $store)->with('status', 'Mall store updated.');
    }

    public function approve(Request $request, MallStore $store): RedirectResponse
    {
        $store->update(['status' => 'active']);
        $store->vendorProfile?->update([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Mall store approved.');
    }

    public function suspend(MallStore $store): RedirectResponse
    {
        $store->update(['status' => 'suspended']);

        return back()->with('status', 'Mall store suspended.');
    }
}
