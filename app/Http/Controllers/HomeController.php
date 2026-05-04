<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Event;
use App\Models\Listing;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $latestArticles = Article::with(['author', 'categories'])
            ->published()
            ->latest('published_at')
            ->limit(5)
            ->get();

        $featuredListings = Listing::with('categories')
            ->published()
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        $upcomingEvents = Event::with(['listing', 'categories'])
            ->published()
            ->where('start_at', '>=', now()->subDay())
            ->orderBy('start_at')
            ->limit(4)
            ->get();

        return view('home.index', [
            'leadArticle' => $latestArticles->first(),
            'secondaryArticles' => $latestArticles->slice(1)->values(),
            'listingCount' => Listing::published()->count(),
            'eventCount' => Event::published()->count(),
            'articleCount' => Article::published()->count(),
            'featuredListings' => $featuredListings,
            'upcomingEvents' => $upcomingEvents,
            'featuredCategories' => Category::query()
                ->where('type', 'listing')
                ->withCount([
                    'listings as visible_listings_count' => fn ($query) => $query->published(),
                ])
                ->orderByDesc('visible_listings_count')
                ->orderBy('name')
                ->limit(6)
                ->get(),
            'latestArticles' => $latestArticles,
            'homeAdCampaigns' => \App\Models\AdCampaign::where('status', 'active')
                ->whereNotNull('creative_image')
                ->inRandomOrder()
                ->limit(3)
                ->get(),
        ]);
    }
}
