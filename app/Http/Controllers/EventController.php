<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $category = trim((string) $request->string('category'));
        $location = trim((string) $request->string('location'));
        $upcomingOnly = ! $request->has('upcoming') || $request->boolean('upcoming');
        $userLat = $request->float('user_lat');
        $userLng = $request->float('user_lng');
        $radius = $request->integer('radius', 50);

        $categories = Category::query()
            ->where('type', 'event')
            ->withCount([
                'events as visible_events_count' => fn ($query) => $query->published(),
            ])
            ->orderByDesc('visible_events_count')
            ->orderBy('name')
            ->get();

        $events = Event::with(['listing', 'categories'])
            ->published()
            ->when($userLat && $userLng, function ($query) use ($userLat, $userLng, $radius) {
                // We use COALESCE to fallback to listing coordinates if event coordinates are null
                $formula = "(6371 * acos(cos(radians(?)) * cos(radians(COALESCE(events.latitude, listings.latitude))) * cos(radians(COALESCE(events.longitude, listings.longitude)) - radians(?)) + sin(radians(?)) * sin(radians(COALESCE(events.latitude, listings.latitude)))))";
                $query->join('listings', 'events.listing_id', '=', 'listings.id')
                    ->selectRaw("events.*, $formula AS distance", [$userLat, $userLng, $userLat])
                    ->whereRaw("$formula <= ?", [$userLat, $userLng, $userLat, $radius]);
            })
            ->when($upcomingOnly, fn ($query) => $query->where('start_at', '>=', now()->startOfDay()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('venue_name', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($categories) => $categories->where('slug', $category));
            })
            ->when($location !== '', function ($query) use ($location) {
                $query->where(function ($inner) use ($location) {
                    $inner->where('city', 'like', "%{$location}%")
                        ->orWhere('region', 'like', "%{$location}%")
                        ->orWhere('venue_name', 'like', "%{$location}%")
                        ->orWhere('address_line', 'like', "%{$location}%");
                });
            })
            ->when($userLat && $userLng, fn ($q) => $q->orderBy('distance'), fn ($q) => $q->orderBy('start_at'))
            ->paginate(12)
            ->withQueryString();

        $mapMarkers = Event::with('listing:id,latitude,longitude,title')
            ->published()
            ->when($userLat && $userLng, function ($query) use ($userLat, $userLng, $radius) {
                $formula = "(6371 * acos(cos(radians(?)) * cos(radians(COALESCE(events.latitude, listings.latitude))) * cos(radians(COALESCE(events.longitude, listings.longitude)) - radians(?)) + sin(radians(?)) * sin(radians(COALESCE(events.latitude, listings.latitude)))))";
                $query->join('listings', 'events.listing_id', '=', 'listings.id')
                    ->selectRaw("events.*, $formula AS distance", [$userLat, $userLng, $userLat])
                    ->whereRaw("$formula <= ?", [$userLat, $userLng, $userLat, $radius]);
            })
            ->when($upcomingOnly, fn ($q) => $q->where('start_at', '>=', now()->startOfDay()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('venue_name', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($c) => $c->where('slug', $category));
            })
            ->when($location !== '', function ($query) use ($location) {
                $query->where(function ($inner) use ($location) {
                    $inner->where('city', 'like', "%{$location}%")
                        ->orWhere('region', 'like', "%{$location}%")
                        ->orWhere('venue_name', 'like', "%{$location}%");
                });
            })
            ->where(function ($q) {
                $q->whereNotNull('events.latitude')
                  ->orWhereHas('listing', fn ($l) => $l->whereNotNull('latitude'));
            })
            ->addSelect(['events.id', 'events.title', 'events.slug', 'events.city', 'events.latitude', 'events.longitude', 'events.start_at', 'events.listing_id'])
            ->orderBy('start_at')
            ->limit(200)
            ->get()
            ->map(fn ($e) => [
                'lat'   => (float) ($e->latitude ?? $e->listing?->latitude),
                'lng'   => (float) ($e->longitude ?? $e->listing?->longitude),
                'title' => $e->title,
                'date'  => optional($e->start_at)->format('j M Y'),
                'city'  => $e->city,
                'distance' => $e->distance ?? null,
                'url'   => route('events.show', $e),
            ])
            ->filter(fn ($m) => $m['lat'] && $m['lng'])
            ->values()
            ->all();

        return view('events.index', [
            'events' => $events,
            'categories' => $categories,
            'featuredCategories' => $categories->take(6)->values(),
            'popularLocations' => Event::published()
                ->selectRaw('city, count(*) as events_count')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->groupBy('city')
                ->orderByDesc('events_count')
                ->orderBy('city')
                ->limit(6)
                ->get(),
            'eventStats' => [
                'visible_events' => Event::published()->count(),
                'upcoming_events' => Event::published()->where('start_at', '>=', now()->startOfDay())->count(),
                'categories' => $categories->count(),
                'results' => $events->total(),
            ],
            'filters' => [
                'q' => $search,
                'category' => $category,
                'location' => $location,
                'upcoming' => $upcomingOnly,
            ],
            'mapMarkers' => $mapMarkers,
        ]);
    }

    public function show(Event $event): View
    {
        abort_if(! $event->isPubliclyVisible(), 404);

        $event->load(['listing.categories', 'owner', 'categories']);

        $categoryIds = $event->categories->modelKeys();

        $relatedEvents = Event::with(['listing', 'categories'])
            ->published()
            ->whereKeyNot($event->getKey())
            ->when(! empty($categoryIds), function ($query) use ($categoryIds) {
                $query->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds));
            })
            ->orderBy('start_at')
            ->limit(3)
            ->get();

        return view('events.show', [
            'event' => $event,
            'relatedEvents' => $relatedEvents,
            'eventStats' => [
                'organiser' => $event->listing?->title,
                'starts' => $event->start_at,
                'ends' => $event->end_at,
                'is_all_day' => $event->is_all_day,
            ],
        ]);
    }
}
