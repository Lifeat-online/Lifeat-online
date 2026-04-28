<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AddListingController extends Controller
{
    public function index(): View
    {
        $directoryPackages = Package::with(['type', 'prices'])
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', 'business_directory'))
            ->orderBy('is_self_service')
            ->get();

        return view('add-listing.index', [
            'directoryPackages' => $directoryPackages,
            'pricing' => [
                'directory_standard_6m' => (float) Setting::getValue('pricing.business_directory_6m', 500),
                'directory_self_service_6m' => (float) Setting::getValue('pricing.business_directory_self_service_6m', 750),
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
            'source_channel' => $package->is_self_service ? 'self_service' : 'staff_assisted',
            'title' => $validated['title'],
            'slug' => $this->uniqueListingSlug($validated['title']),
            'city' => $validated['city'] ?: null,
            'status' => 'draft',
        ]);

        return redirect()->route('checkout.index', [
            'listing' => $listing->slug,
            'package' => $package->slug,
        ])->with('status', 'Listing starter created. Choose your package to continue.');
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
}
