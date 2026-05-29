<?php

namespace App\Http\Controllers\Mall\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MallStore;
use App\Models\MallStoreCategory;
use App\Models\MallVendorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorRegistrationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->mallStore?->status === 'active') {
            return redirect()->route('mall.vendor.dashboard');
        }

        if ($request->user()->mallStore) {
            return view('mall.vendor.pending', [
                'store' => $request->user()->mallStore,
            ]);
        }

        $categories = MallStoreCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('mall.vendor.register', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'payfast_merchant_id' => ['nullable', 'string', 'max:20'],
            'payfast_merchant_key' => ['nullable', 'string', 'max:20'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:mall_store_categories,id'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'business_reg' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'bank_branch_code' => ['nullable', 'string', 'max:10'],
        ]);

        $store = MallStore::create([
            'owner_user_id' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueStoreSlug($validated['name']),
            'tagline' => $validated['tagline'] ?? null,
            'description' => $validated['description'] ?? null,
            'primary_color' => $validated['primary_color'] ?? '#3B82F6',
            'payfast_merchant_id' => $validated['payfast_merchant_id'] ?? null,
            'payfast_merchant_key' => $validated['payfast_merchant_key'] ?? null,
            'status' => 'pending',
        ]);

        $store->categories()->sync($validated['category_ids'] ?? []);

        MallVendorProfile::create([
            'mall_store_id' => $store->id,
            'user_id' => $request->user()->id,
            'contact_name' => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'] ?? null,
            'business_reg' => $validated['business_reg'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account' => $validated['bank_account'] ?? null,
            'bank_branch_code' => $validated['bank_branch_code'] ?? null,
        ]);

        return redirect()
            ->route('mall.vendor.register')
            ->with('status', 'Your mall store application has been submitted.');
    }

    private function uniqueStoreSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'mall-store';
        $slug = $base;
        $index = 2;

        while (MallStore::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }
}
