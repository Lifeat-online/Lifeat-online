<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Voucher;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            $this->entry(route('home'), priority: '1.0'),
            $this->entry(route('directory.index'), changefreq: 'daily', priority: '0.9'),
            $this->entry(route('events.index'), changefreq: 'daily', priority: '0.9'),
            $this->entry(route('articles.index'), changefreq: 'daily', priority: '0.9'),
            $this->entry(route('vouchers.index'), changefreq: 'daily', priority: '0.8'),
            $this->entry(route('classifieds.index'), changefreq: 'daily', priority: '0.7'),
            $this->entry(route('advertise.index'), changefreq: 'monthly', priority: '0.7'),
            $this->entry(route('add-listing.index'), changefreq: 'monthly', priority: '0.7'),
            $this->entry(route('transport.index'), changefreq: 'weekly', priority: '0.7'),
            $this->entry(route('about.index'), changefreq: 'monthly', priority: '0.5'),
            $this->entry(route('contact.index'), changefreq: 'monthly', priority: '0.5'),
            $this->entry(route('legal.terms'), changefreq: 'monthly', priority: '0.4'),
            $this->entry(route('legal.privacy'), changefreq: 'monthly', priority: '0.4'),
        ])
            ->merge($this->listingEntries())
            ->merge($this->eventEntries())
            ->merge($this->articleEntries())
            ->merge($this->voucherEntries())
            ->merge($this->classifiedEntries())
            ->values();

        return response()
            ->view('seo.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function listingEntries(): Collection
    {
        return Listing::query()
            ->published()
            ->select(['id', 'slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->map(fn (Listing $listing): array => $this->entry(
                route('directory.show', $listing),
                $listing->updated_at?->toAtomString(),
                'weekly',
                '0.8'
            ));
    }

    private function eventEntries(): Collection
    {
        return Event::query()
            ->published()
            ->select(['id', 'slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->map(fn (Event $event): array => $this->entry(
                route('events.show', $event),
                $event->updated_at?->toAtomString(),
                'weekly',
                '0.8'
            ));
    }

    private function articleEntries(): Collection
    {
        return Article::query()
            ->published()
            ->select(['id', 'slug', 'updated_at', 'published_at'])
            ->latest('published_at')
            ->limit(500)
            ->get()
            ->map(fn (Article $article): array => $this->entry(
                route('articles.show', $article),
                ($article->updated_at ?: $article->published_at)?->toAtomString(),
                'weekly',
                '0.8'
            ));
    }

    private function voucherEntries(): Collection
    {
        return Voucher::query()
            ->active()
            ->with('listing:id,slug')
            ->whereHas('listing', fn ($query) => $query->published())
            ->select(['id', 'listing_id', 'slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->map(fn (Voucher $voucher): array => $this->entry(
                route('vouchers.show', [$voucher->listing, $voucher]),
                $voucher->updated_at?->toAtomString(),
                'weekly',
                '0.7'
            ));
    }

    private function classifiedEntries(): Collection
    {
        return Classified::query()
            ->where('status', Classified::STATUS_PUBLISHED)
            ->select(['id', 'slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->map(fn (Classified $classified): array => $this->entry(
                route('classifieds.show', $classified),
                $classified->updated_at?->toAtomString(),
                'weekly',
                '0.6'
            ));
    }

    private function entry(string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.6'): array
    {
        return compact('loc', 'lastmod', 'changefreq', 'priority');
    }
}
