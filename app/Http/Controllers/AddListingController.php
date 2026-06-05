<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Package;
use App\Support\Caching\PublicReadCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AddListingController extends Controller
{
    public function index(): View
    {
        $directoryPackages = PublicReadCache::activePackagesForType('business_directory', 'self_service');
        $staffAssistedPackage = $directoryPackages->first(fn (array $package): bool => ! $package['is_self_service']);
        $selfServicePackage = $directoryPackages->first(fn (array $package): bool => $package['is_self_service']);

        return view('add-listing.index', [
            'directoryPackages' => $directoryPackages,
            'pricing' => [
                'directory_standard_6m' => $this->packageAmount($staffAssistedPackage, 500),
                'directory_self_service_6m' => $this->packageAmount($selfServicePackage, 750),
            ],
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'package_slug' => ['required', 'string', 'exists:packages,slug'],
        ]);

        $package = Package::with('type')
            ->active()
            ->where('slug', $validated['package_slug'])
            ->whereHas('type', fn ($query) => $query->where('slug', 'business_directory'))
            ->firstOrFail();

        $listing = Listing::create([
            'user_id' => $request->user()->id,
            'registered_by_user_id' => $request->user()->hasRole('staff') ? $request->user()->id : null,
            'source_channel' => $package->is_self_service ? 'self_service' : 'staff_assisted',
            'title' => $validated['title'],
            'slug' => $this->uniqueListingSlug($validated['title']),
            'city' => $validated['city'] ?: null,
            'status' => 'draft',
        ]);

        return redirect()->route('checkout.index', [
            'listing' => $listing->slug,
            'package' => $package->slug,
        ])->with('status', 'Listing starter created. Confirm your package and create the order to continue activation.');
    }

    private function uniqueListingSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'listing';
        $counter = 2;

        while (Listing::where('slug', $slug)->exists()) {
            $slug = ($base !== '' ? $base : 'listing').'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function packageAmount(?array $package, float $fallback): float
    {
        return (float) ($package['current_price']['amount'] ?? $fallback);
    }
}
