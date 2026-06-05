<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Voucher;
use App\Support\Caching\PublicReadCache;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $publicStats = PublicReadCache::publicStats();
        $latestArticles = Article::with(['author', 'contentTranslations', 'categories.contentTranslations'])
            ->published()
            ->latest('published_at')
            ->limit(5)
            ->get();

        $featuredListings = Listing::with(['contentTranslations', 'categories.contentTranslations'])
            ->published()
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        $upcomingEvents = Event::with(['contentTranslations', 'listing.contentTranslations', 'categories.contentTranslations'])
            ->published()
            ->where('start_at', '>=', now()->subDay())
            ->orderBy('start_at')
            ->limit(4)
            ->get();

        $featuredVouchers = Voucher::query()
            ->with([
                'contentTranslations',
                'listing.contentTranslations',
                'categories.contentTranslations',
            ])
            ->active()
            ->whereHas('listing', fn ($listing) => $listing->where('status', 'published'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return view('home.index', [
            'leadArticle' => $latestArticles->first(),
            'secondaryArticles' => $latestArticles->slice(1)->values(),
            'listingCount' => $publicStats['visible_listings'],
            'eventCount' => $publicStats['visible_events'],
            'articleCount' => $publicStats['published_articles'],
            'featuredListings' => $featuredListings,
            'upcomingEvents' => $upcomingEvents,
            'featuredVouchers' => $featuredVouchers,
            'featuredCategories' => PublicReadCache::listingCategories()->take(6)->values(),
            'latestArticles' => $latestArticles,
            'homeAdCampaigns' => \App\Models\AdCampaign::where('status', 'active')
                ->whereNotNull('creative_image')
                ->inRandomOrder()
                ->limit(3)
                ->get(),
        ]);
    }
}
