<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q'));
        $location = trim((string) $request->string('loc'));
        $category = trim((string) $request->string('category'));

        $listings = Listing::with('categories')
            ->published()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('excerpt', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%");
                });
            })
            ->when($location !== '', function ($query) use ($location) {
                $query->where(function ($inner) use ($location) {
                    $inner->where('city', 'like', "%{$location}%")
                        ->orWhere('region', 'like', "%{$location}%")
                        ->orWhere('country', 'like', "%{$location}%");
                });
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($cats) => $cats->where('slug', $category));
            })
            ->orderByDesc('is_featured')
            ->orderBy('title')
            ->paginate(10, ['*'], 'listings_page')
            ->withQueryString();

        $events = Event::with(['categories', 'listing'])
            ->published()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('excerpt', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%");
                });
            })
            ->when($location !== '', function ($query) use ($location) {
                $query->where(function ($inner) use ($location) {
                    $inner->where('city', 'like', "%{$location}%")
                        ->orWhere('region', 'like', "%{$location}%")
                        ->orWhere('country', 'like', "%{$location}%");
                });
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($cats) => $cats->where('slug', $category));
            })
            ->orderBy('start_at')
            ->paginate(10, ['*'], 'events_page')
            ->withQueryString();

        $articles = Article::with(['author', 'categories'])
            ->published()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('excerpt', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%");
                });
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($cats) => $cats->where('slug', $category));
            })
            ->latest('published_at')
            ->paginate(10, ['*'], 'articles_page')
            ->withQueryString();

        $classifieds = Classified::query()
            ->where('status', 'published')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%");
                });
            })
            ->when($location !== '', function ($query) use ($location) {
                $query->where(function ($inner) use ($location) {
                    $inner->where('city', 'like', "%{$location}%")
                        ->orWhere('region', 'like', "%{$location}%")
                        ->orWhere('country', 'like', "%{$location}%");
                });
            })
            ->when($category !== '', function ($query) {
                // Classified category filtering is not implemented yet, so avoid mixing
                // unfiltered classifieds into a category-specific result set.
                $query->whereRaw('1 = 0');
            })
            ->latest('published_at')
            ->paginate(10, ['*'], 'classifieds_page')
            ->withQueryString();

        return view('search.index', [
            'filters' => [
                'q' => $q,
                'loc' => $location,
                'category' => $category,
            ],
            'categories' => Category::query()
                ->whereIn('type', ['listing', 'event', 'article'])
                ->orderBy('name')
                ->get(),
            'listings' => $listings,
            'events' => $events,
            'articles' => $articles,
            'classifieds' => $classifieds,
        ]);
    }
}
