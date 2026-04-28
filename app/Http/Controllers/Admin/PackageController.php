<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PackageType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function index(): View
    {
        return view('admin.packages.index', [
            'packages' => Package::with(['type', 'prices'])->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.form', [
            'package' => new Package(),
            'packagePrice' => new PackagePrice(),
            'packageTypes' => PackageType::orderBy('name')->get(),
            'formAction' => route('admin.packages.store'),
            'formMethod' => 'POST',
            'pageTitle' => 'Create Package',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $package = Package::create($data);
        $this->syncPrice($package, $request);

        return redirect()->route('admin.packages.edit', $package)->with('status', 'Package created.');
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', [
            'package' => $package->load(['type', 'prices']),
            'packagePrice' => $package->currentPrice() ?? new PackagePrice(),
            'packageTypes' => PackageType::orderBy('name')->get(),
            'formAction' => route('admin.packages.update', $package),
            'formMethod' => 'PUT',
            'pageTitle' => 'Edit Package',
        ]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $data = $this->validated($request, $package);
        $package->update($data);
        $this->syncPrice($package, $request);

        return redirect()->route('admin.packages.edit', $package)->with('status', 'Package updated.');
    }

    private function validated(Request $request, ?Package $package = null): array
    {
        return $request->validate([
            'package_type_id' => ['required', 'exists:package_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('packages', 'slug')->ignore($package?->id)],
            'description' => ['nullable', 'string'],
            'billing_model' => ['required', Rule::in(['once_off', 'monthly', 'six_monthly'])],
            'is_self_service' => ['nullable', 'boolean'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'vat_inclusive' => ['nullable', 'boolean'],
        ]);
    }

    private function syncPrice(Package $package, Request $request): void
    {
        $price = $package->currentPrice();

        $attributes = [
            'currency' => $request->string('currency')->toString(),
            'amount' => $request->input('amount'),
            'vat_inclusive' => $request->boolean('vat_inclusive', true),
            'effective_from' => $price?->effective_from ?? now(),
            'created_by_user_id' => $request->user()?->id,
        ];

        if ($price) {
            $price->update($attributes);
            return;
        }

        $package->prices()->create($attributes);
    }
}
