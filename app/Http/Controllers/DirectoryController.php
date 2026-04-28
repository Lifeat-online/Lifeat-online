<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $category = trim((string) $request->string('category'));
        $location = trim((string) $request->string('location'));
        $featuredOnly = $request->boolean('featured');
        $userLat = $request->float('user_lat');
        $userLng = $request->float('user_lng');
        $radius = $request->integer('radius', 50); // Default 50km

        $categories = Category::query()
            ->where('type', 'listing')
            ->withCount([
                'listings as visible_listings_count' => fn ($query) => $query->published(),
            ])
            ->orderByDesc('visible_listings_count')
            ->orderBy('name')
            ->get();

        $listings = Listing::with('categories')
            ->withCount(['reviews' => fn ($query) => $query->where('status', 'approved')])
            ->withAvg(['reviews' => fn ($query) => $query->where('status', 'approved')], 'rating')
            ->published()
            ->when($userLat && $userLng, function ($query) use ($userLat, $userLng, $radius) {
                $formula = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
                $query->selectRaw("*, $formula AS distance", [$userLat, $userLng, $userLat])
                    ->whereRaw("$formula <= ?", [$userLat, $userLng, $userLat, $radius]);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($categories) => $categories->where('slug', $category));
            })
            ->when($location !== '', function ($query) use ($location) {
                $query->where(function ($inner) use ($location) {
                    $inner->where('city', 'like', "%{$location}%")
                        ->orWhere('region', 'like', "%{$location}%")
                        ->orWhere('address_line', 'like', "%{$location}%");
                });
            })
            ->when($featuredOnly, fn ($query) => $query->where('is_featured', true))
            ->when($userLat && $userLng, fn ($q) => $q->orderBy('distance'), fn ($q) => $q->orderByDesc('is_featured'))
            ->orderByDesc('reviews_avg_rating')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $mapMarkers = Listing::published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($userLat && $userLng, function ($query) use ($userLat, $userLng, $radius) {
                $formula = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
                $query->selectRaw("*, $formula AS distance", [$userLat, $userLng, $userLat])
                    ->whereRaw("$formula <= ?", [$userLat, $userLng, $userLat, $radius]);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($c) => $c->where('slug', $category));
            })
            ->when($location !== '', function ($query) use ($location) {
                $query->where(function ($inner) use ($location) {
                    $inner->where('city', 'like', "%{$location}%")
                        ->orWhere('region', 'like', "%{$location}%");
                });
            })
            ->when($featuredOnly, fn ($query) => $query->where('is_featured', true))
            ->addSelect(['listings.id', 'listings.title', 'listings.slug', 'listings.city', 'listings.latitude', 'listings.longitude', 'listings.is_featured'])
            ->orderByDesc('is_featured')
            ->limit(200)
            ->get()
            ->map(fn ($l) => [
                'lat'      => (float) $l->latitude,
                'lng'      => (float) $l->longitude,
                'title'    => $l->title,
                'city'     => $l->city,
                'featured' => (bool) $l->is_featured,
                'distance' => $l->distance ?? null,
                'url'      => route('directory.show', $l),
            ])
            ->values()
            ->all();

        return view('directory.index', [
            'listings' => $listings,
            'categories' => $categories,
            'featuredCategories' => $categories->take(6)->values(),
            'popularLocations' => Listing::published()
                ->selectRaw('city, count(*) as listings_count')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->groupBy('city')
                ->orderByDesc('listings_count')
                ->orderBy('city')
                ->limit(6)
                ->get(),
            'directoryStats' => [
                'visible_listings' => Listing::published()->count(),
                'featured_listings' => Listing::published()->where('is_featured', true)->count(),
                'categories' => $categories->count(),
                'results' => $listings->total(),
            ],
            'filters' => [
                'q' => $search,
                'category' => $category,
                'location' => $location,
                'featured' => $featuredOnly,
            ],
            'sidebarAdCampaigns' => AdCampaign::with('listing')
                ->where('status', 'active')
                ->whereNotNull('creative_image')
                ->inRandomOrder()
                ->limit(2)
                ->get(),
            'mapMarkers' => $mapMarkers,
        ]);
    }

    public function show(Listing $listing): View
    {
        abort_if(! $listing->isPubliclyVisible(), 404);

        $listing->load([
            'categories',
            'owner',
            'photos',
            'events' => fn ($query) => $query->published()->orderBy('start_at')->limit(3),
            'reviews' => fn ($query) => $query->where('status', 'approved')->latest(),
            'reviews.author',
        ])->loadCount([
            'reviews' => fn ($query) => $query->where('status', 'approved'),
        ])->loadAvg([
            'reviews' => fn ($query) => $query->where('status', 'approved'),
        ], 'rating');

        $categoryIds = $listing->categories->modelKeys();

        $relatedListings = Listing::with('categories')
            ->withCount(['reviews' => fn ($query) => $query->where('status', 'approved')])
            ->published()
            ->whereKeyNot($listing->getKey())
            ->when(! empty($categoryIds), function ($query) use ($categoryIds) {
                $query->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds));
            })
            ->orderByDesc('is_featured')
            ->orderBy('title')
            ->limit(3)
            ->get();

        $activeCampaign = $listing->adCampaigns()->where('status', 'active')->latest()->first();

        return view('directory.show', [
            'listing' => $listing,
            'activeCampaign' => $activeCampaign,
            'relatedListings' => $relatedListings,
            'profileStats' => [
                'reviews' => $listing->reviews_count,
                'average_rating' => $listing->reviews_avg_rating,
                'events' => $listing->events->count(),
            ],
        ]);
    }
}
