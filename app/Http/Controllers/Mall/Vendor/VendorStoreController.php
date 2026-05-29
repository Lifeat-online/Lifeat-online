<?php

namespace App\Http\Controllers\Mall\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MallStoreCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorStoreController extends Controller
{
    public function edit(Request $request): View
    {
        $store = $request->user()->mallStore->load('categories', 'vendorProfile');
        $categories = MallStoreCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('mall.vendor.store.edit', compact('store', 'categories'));
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->mallStore;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pickup_address' => ['nullable', 'string', 'max:500'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-35,-22'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:16,33'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'payfast_merchant_id' => ['nullable', 'string', 'max:20'],
            'payfast_merchant_key' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'banner' => ['nullable', 'image', 'max:8192'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:mall_store_categories,id'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('mall/stores/logos', 'public');
        }

        if ($request->hasFile('banner')) {
            $validated['banner_path'] = $request->file('banner')->store('mall/stores/banners', 'public');
        }

        $store->update(collect($validated)->except(['category_ids', 'logo', 'banner'])->all());
        $store->categories()->sync($validated['category_ids'] ?? []);

        return back()->with('status', 'Store profile updated.');
    }
}
