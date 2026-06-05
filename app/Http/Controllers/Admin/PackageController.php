<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PackageType;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    private const PACKAGE_FIELDS = [
        'package_type_id',
        'name',
        'slug',
        'description',
        'billing_model',
        'is_self_service',
        'duration_days',
        'status',
    ];

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

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        [$packageData, $priceData] = $this->splitValidatedData($data);

        $package = DB::transaction(function () use ($request, $audit, $packageData, $priceData): Package {
            $package = Package::create($packageData);
            $price = $this->createPrice($package, $priceData, $request);

            $audit->log($request, 'package.created', $package, [], $this->packageSnapshot($package));
            $audit->log($request, 'package_price.created', $price, [], $this->priceSnapshot($price));

            return $package;
        });

        return redirect()->route('admin.packages.edit', $package)->with('status', 'Package created.');
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', [
            'package' => $package->load(['type', 'prices.creator']),
            'packagePrice' => $package->currentPrice() ?? new PackagePrice(),
            'packageTypes' => PackageType::orderBy('name')->get(),
            'formAction' => route('admin.packages.update', $package),
            'formMethod' => 'PUT',
            'pageTitle' => 'Edit Package',
        ]);
    }

    public function update(Request $request, Package $package, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request, $package);
        [$packageData, $priceData] = $this->splitValidatedData($data);

        DB::transaction(function () use ($request, $package, $audit, $packageData, $priceData): void {
            $before = $this->packageSnapshot($package);

            $package->update($packageData);
            $package->refresh();

            $after = $this->packageSnapshot($package);
            if ($before !== $after) {
                $audit->log($request, 'package.updated', $package, $before, $after);
            }

            $this->syncPrice($package, $priceData, $request, $audit);
        });

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
            'price_change_note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function splitValidatedData(array $data): array
    {
        $packageData = array_intersect_key($data, array_flip(self::PACKAGE_FIELDS));
        $packageData['is_self_service'] = (bool) ($data['is_self_service'] ?? false);

        $priceData = [
            'currency' => strtoupper((string) $data['currency']),
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'vat_inclusive' => (bool) ($data['vat_inclusive'] ?? false),
            'price_change_note' => trim((string) ($data['price_change_note'] ?? '')),
        ];

        return [$packageData, $priceData];
    }

    private function syncPrice(Package $package, array $priceData, Request $request, AuditLogService $audit): void
    {
        $price = $package->currentPrice();

        if (! $price) {
            $newPrice = $this->createPrice($package, $priceData, $request);
            $audit->log($request, 'package_price.created', $newPrice, [], $this->priceSnapshot($newPrice));

            return;
        }

        if (! $this->priceChanged($price, $priceData)) {
            return;
        }

        if ($priceData['price_change_note'] === '') {
            throw ValidationException::withMessages([
                'price_change_note' => 'Add a pricing authority note before changing the active price.',
            ]);
        }

        $before = $this->priceSnapshot($price);
        $effectiveAt = now();

        $price->update([
            'effective_to' => $effectiveAt,
        ]);

        $newPrice = $this->createPrice($package, $priceData, $request, $effectiveAt);

        $audit->log($request, 'package_price.versioned', $newPrice, $before, $this->priceSnapshot($newPrice) + [
            'replaces_package_price_id' => $price->id,
            'change_note' => $priceData['price_change_note'],
        ]);
    }

    private function createPrice(Package $package, array $priceData, Request $request, mixed $effectiveAt = null): PackagePrice
    {
        return $package->prices()->create([
            'currency' => $priceData['currency'],
            'amount' => $priceData['amount'],
            'vat_inclusive' => $priceData['vat_inclusive'],
            'effective_from' => $effectiveAt ?? now(),
            'created_by_user_id' => $request->user()?->id,
        ]);
    }

    private function priceChanged(PackagePrice $price, array $priceData): bool
    {
        return strtoupper((string) $price->currency) !== $priceData['currency']
            || number_format((float) $price->amount, 2, '.', '') !== $priceData['amount']
            || (bool) $price->vat_inclusive !== $priceData['vat_inclusive'];
    }

    private function packageSnapshot(Package $package): array
    {
        return $package->only(self::PACKAGE_FIELDS);
    }

    private function priceSnapshot(PackagePrice $price): array
    {
        return [
            'id' => $price->id,
            'package_id' => $price->package_id,
            'currency' => $price->currency,
            'amount' => number_format((float) $price->amount, 2, '.', ''),
            'vat_inclusive' => (bool) $price->vat_inclusive,
            'effective_from' => $price->effective_from?->toISOString(),
            'effective_to' => $price->effective_to?->toISOString(),
            'created_by_user_id' => $price->created_by_user_id,
        ];
    }
}
