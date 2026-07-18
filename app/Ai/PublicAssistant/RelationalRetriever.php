<?php

namespace App\Ai\PublicAssistant;

use App\Models\Article;
use App\Models\CivicFaultReport;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Voucher;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RelationalRetriever
{
    public function retrieve(array $sourceTypes, array $terms, array $search, string $locale, Closure $translate): Collection
    {
        $sources = collect();
        foreach ($sourceTypes as $type) {
            $sources = $sources->merge(match ($type) {
                'business' => $this->listings($terms, $search, $locale),
                'event' => $this->events($terms, $search, $locale),
                'article' => $this->articles($terms, $locale),
                'voucher' => $this->vouchers($terms, $search, $locale),
                'classified' => $this->classifieds($terms, $search, $locale, $translate),
                'fault' => $this->faults($terms, $search),
                default => collect(),
            });
        }

        return $sources->unique('id')->values();
    }

    private function listings(array $terms, array $search, string $locale): Collection
    {
        return Listing::with(['contentTranslations', 'categories.contentTranslations'])->published()
            ->where(function (Builder $query) use ($terms): void {
                $this->applyTermSearch($query, ['title', 'excerpt', 'description', 'city', 'region'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['city', 'region', 'country', 'address_line'], (string) $search['location']))
            ->orderByDesc('is_featured')->latest('published_at')->limit(6)->get()
            ->map(fn (Listing $listing): array => [
                'id' => 'listing:'.$listing->id, 'type' => 'business',
                'title' => $listing->localizedValue('title', $locale),
                'summary' => $this->summary($listing->localizedValue('excerpt', $locale) ?: $listing->localizedValue('description', $locale)),
                'location' => $this->location([$listing->localizedValue('city', $locale), $listing->localizedValue('region', $locale)]),
                'url' => route('directory.show', $listing),
                'meta' => [
                    'categories' => $listing->categories->map(fn ($category) => $category->localizedValue('name', $locale))->values()->all(),
                    'phone' => $listing->phone, 'website' => $listing->website_url, 'status' => $listing->status,
                    'featured' => $listing->is_featured,
                    'address' => $this->location([$listing->localizedValue('address_line', $locale), $listing->localizedValue('city', $locale), $listing->localizedValue('region', $locale)]),
                ],
            ]);
    }

    private function events(array $terms, array $search, string $locale): Collection
    {
        return Event::with(['contentTranslations', 'categories.contentTranslations', 'listing.contentTranslations'])->published()
            ->where(function (Builder $query) use ($terms): void {
                $this->applyTermSearch($query, ['title', 'excerpt', 'description', 'venue_name', 'city', 'region'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['venue_name', 'city', 'region', 'country', 'address_line'], (string) $search['location']))
            ->when(! empty($search['time_window']), fn (Builder $query) => $query->whereBetween('start_at', [$search['time_window']['start'], $search['time_window']['end']]))
            ->orderBy('start_at')->limit(6)->get()->map(fn (Event $event): array => [
                'id' => 'event:'.$event->id, 'type' => 'event', 'title' => $event->localizedValue('title', $locale),
                'summary' => $this->summary($event->localizedValue('excerpt', $locale) ?: $event->localizedValue('description', $locale)),
                'location' => $this->location([$event->localizedValue('venue_name', $locale), $event->localizedValue('city', $locale), $event->localizedValue('region', $locale)]),
                'url' => route('events.show', $event),
                'meta' => ['date' => $event->start_at?->format('Y-m-d H:i'), 'ends_at' => $event->end_at?->format('Y-m-d H:i'), 'categories' => $event->categories->map(fn ($category) => $category->localizedValue('name', $locale))->values()->all(), 'business' => $event->listing?->localizedValue('title', $locale), 'status' => $event->status, 'website' => $event->website_url],
            ]);
    }

    private function articles(array $terms, string $locale): Collection
    {
        return Article::with(['author', 'contentTranslations', 'categories.contentTranslations'])->published()
            ->where(function (Builder $query) use ($terms): void {
                $this->applyTermSearch($query, ['title', 'excerpt', 'body'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })->latest('published_at')->limit(6)->get()->map(fn (Article $article): array => [
                'id' => 'article:'.$article->id, 'type' => 'article', 'title' => $article->localizedTitle($locale),
                'summary' => $this->summary($article->localizedExcerpt($locale) ?: $article->localizedBody($locale)), 'location' => null,
                'url' => route('articles.show', $article),
                'meta' => ['published' => $article->published_at?->format('Y-m-d'), 'author' => $article->author?->name, 'categories' => $article->categories->map(fn ($category) => $category->localizedValue('name', $locale))->values()->all(), 'status' => $article->status],
            ]);
    }

    private function vouchers(array $terms, array $search, string $locale): Collection
    {
        return Voucher::with(['contentTranslations', 'listing.contentTranslations'])->active()->whereHas('listing', fn (Builder $listing) => $listing->published())
            ->where(function (Builder $query) use ($terms): void {
                $this->applyTermSearch($query, ['title', 'description', 'terms'], $terms);
                $query->orWhereHas('listing', fn (Builder $listing) => $this->applyTermSearch($listing, ['title', 'city', 'region'], $terms));
            })->when(filled($search['location'] ?? null), fn (Builder $query) => $query->whereHas('listing', fn (Builder $listing) => $this->applyLocationSearch($listing, ['city', 'region', 'country', 'address_line'], (string) $search['location'])))
            ->latest('published_at')->limit(5)->get()->map(fn (Voucher $voucher): array => [
                'id' => 'voucher:'.$voucher->id, 'type' => 'voucher', 'title' => $voucher->localizedValue('title', $locale),
                'summary' => $this->summary($voucher->localizedValue('description', $locale) ?: $voucher->localizedValue('terms', $locale)),
                'location' => $this->location([$voucher->listing?->localizedValue('city', $locale), $voucher->listing?->localizedValue('region', $locale)]),
                'url' => route('vouchers.show', [$voucher->listing, $voucher]),
                'meta' => ['business' => $voucher->listing?->localizedValue('title', $locale), 'value' => $voucher->formattedValue(), 'ends_at' => $voucher->end_at?->format('Y-m-d'), 'status' => $voucher->status, 'remaining' => $voucher->remainingUses(), 'phone' => $voucher->listing?->phone],
            ]);
    }

    private function classifieds(array $terms, array $search, string $locale, Closure $translate): Collection
    {
        return Classified::with('contentTranslations')->where('status', Classified::STATUS_PUBLISHED)
            ->where(fn (Builder $query) => $this->applyTermSearch($query, ['title', 'description', 'city', 'region'], $terms))
            ->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['city', 'region', 'country'], (string) $search['location']))
            ->latest('published_at')->limit(5)->get()->map(fn (Classified $classified): array => [
                'id' => 'classified:'.$classified->id, 'type' => 'classified', 'title' => $classified->localizedValue('title', $locale),
                'summary' => $this->summary($classified->localizedValue('description', $locale)),
                'location' => $this->location([$classified->localizedValue('city', $locale), $classified->localizedValue('region', $locale)]),
                'url' => route('classifieds.show', $classified),
                'meta' => ['price' => $classified->contact_for_price ? $translate('meta.contact_for_price', $locale) : ($classified->price !== null ? $classified->currency.' '.number_format((float) $classified->price, 2) : null), 'status' => $classified->status],
            ]);
    }

    private function faults(array $terms, array $search): Collection
    {
        return CivicFaultReport::query()->where('is_approved', true)
            ->where(fn (Builder $query) => $this->applyTermSearch($query, ['category', 'severity', 'status', 'address_label', 'description'], $terms))
            ->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['address_label'], (string) $search['location']))
            ->latest()->limit(5)->get()->map(fn (CivicFaultReport $fault): array => [
                'id' => 'fault:'.$fault->id, 'type' => 'fault', 'title' => (CivicFaultReport::categories()[$fault->category] ?? Str::headline($fault->category)).' near '.$fault->address_label,
                'summary' => $this->summary($fault->description), 'location' => $fault->address_label,
                'url' => route('faults.index', ['category' => $fault->category, 'status' => $fault->status]),
                'meta' => ['status' => CivicFaultReport::statuses()[$fault->status] ?? $fault->status, 'severity' => CivicFaultReport::severities()[$fault->severity] ?? $fault->severity, 'reported' => $fault->created_at?->format('Y-m-d')],
            ]);
    }

    private function applyLocationSearch(Builder $query, array $columns, string $location): void
    {
        $query->where(fn (Builder $inner) => collect($columns)->each(fn (string $column) => $inner->orWhere($column, 'like', '%'.$location.'%')));
    }

    private function applyTermSearch(Builder $query, array $columns, array $terms): void
    {
        foreach ($terms as $term) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        }
    }

    private function summary(?string $value): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value))), 260);
    }

    private function location(array $parts): ?string
    {
        $location = collect($parts)->filter()->unique()->implode(', ');

        return $location !== '' ? $location : null;
    }
}
