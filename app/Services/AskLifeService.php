<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CivicFaultReport;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class AskLifeService
{
    public function __construct(
        private readonly AiGatewayService $gateway,
        private readonly AiPromptCatalog $prompts,
    ) {
    }

    public function answer(string $question, ?User $user = null): array
    {
        $question = trim($question);
        $sources = $this->sourcesForQuestion($question);

        if ($sources->isEmpty()) {
            $answer = 'I could not find a direct Life@ match yet. Try a more specific town, business type, event name, article topic, voucher, classified item, or fault category.';

            return [
                'ok' => true,
                'source' => 'fallback',
                'answer' => $answer,
                'locale' => $this->detectLocale($question, $answer),
                'confidence' => 0,
                'sources' => [],
                'follow_up_questions' => [
                    'Which town should I search in?',
                    'Are you looking for a business, event, article, voucher, classified, or fault report?',
                ],
                'search_url' => route('search.index', ['q' => $question]),
            ];
        }

        if (! $this->gateway->configured()) {
            return $this->fallbackAnswer($question, $sources, 'AI provider is not configured yet.');
        }

        $prompt = $this->prompts->get('ask_life');

        try {
            $result = $this->gateway->generateStructured(
                'ask_life',
                $prompt['version'],
                $prompt['system'],
                [
                    'question' => $question,
                    'sources' => $sources->values()->all(),
                    'schema' => $prompt['schema'],
                ],
                null,
                $user,
                'en',
            );

            if (($result['ok'] ?? false) && filled(data_get($result, 'payload.answer'))) {
                $usedIds = collect(data_get($result, 'payload.source_ids', []))
                    ->filter(fn ($id) => is_string($id) && $id !== '')
                    ->values();

                $rankedSources = $usedIds->isEmpty()
                    ? $sources
                    : $sources
                        ->sortBy(fn (array $source) => $usedIds->search($source['id']) === false ? 999 : $usedIds->search($source['id']))
                        ->values();

                return [
                    'ok' => true,
                    'source' => 'ai',
                    'answer' => (string) data_get($result, 'payload.answer'),
                    'locale' => $this->detectLocale((string) data_get($result, 'payload.answer'), $question),
                    'confidence' => (float) data_get($result, 'payload.confidence', 0.65),
                    'sources' => $rankedSources->take(8)->values()->all(),
                    'follow_up_questions' => collect(data_get($result, 'payload.follow_up_questions', []))->take(3)->values()->all(),
                    'generation_id' => data_get($result, 'generation.id'),
                    'search_url' => route('search.index', ['q' => $question]),
                ];
            }

            return $this->fallbackAnswer($question, $sources, $result['message'] ?? 'AI provider did not return a usable answer.');
        } catch (Throwable $exception) {
            return $this->fallbackAnswer($question, $sources, $exception->getMessage());
        }
    }

    public function sourcesForQuestion(string $question): Collection
    {
        $terms = $this->terms($question);

        if ($terms === []) {
            return collect();
        }

        return collect()
            ->merge($this->listingSources($terms))
            ->merge($this->eventSources($terms))
            ->merge($this->articleSources($terms))
            ->merge($this->voucherSources($terms))
            ->merge($this->classifiedSources($terms))
            ->merge($this->faultSources($terms))
            ->take(18)
            ->values();
    }

    private function listingSources(array $terms): Collection
    {
        return Listing::with('categories')
            ->published()
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'excerpt', 'description', 'city', 'region'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->limit(4)
            ->get()
            ->map(fn (Listing $listing): array => [
                'id' => 'listing:'.$listing->id,
                'type' => 'business',
                'title' => $listing->title,
                'summary' => $this->summary($listing->excerpt ?: $listing->description),
                'location' => $this->location([$listing->city, $listing->region]),
                'url' => route('directory.show', $listing),
                'meta' => [
                    'categories' => $listing->categories->pluck('name')->values()->all(),
                    'phone' => $listing->phone,
                    'website' => $listing->website_url,
                ],
            ]);
    }

    private function eventSources(array $terms): Collection
    {
        return Event::with(['categories', 'listing'])
            ->published()
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'excerpt', 'description', 'venue_name', 'city', 'region'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })
            ->orderBy('start_at')
            ->limit(4)
            ->get()
            ->map(fn (Event $event): array => [
                'id' => 'event:'.$event->id,
                'type' => 'event',
                'title' => $event->title,
                'summary' => $this->summary($event->excerpt ?: $event->description),
                'location' => $this->location([$event->venue_name, $event->city, $event->region]),
                'url' => route('events.show', $event),
                'meta' => [
                    'date' => $event->start_at?->format('Y-m-d H:i'),
                    'categories' => $event->categories->pluck('name')->values()->all(),
                    'business' => $event->listing?->title,
                ],
            ]);
    }

    private function articleSources(array $terms): Collection
    {
        return Article::with(['author', 'categories'])
            ->published()
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'excerpt', 'body'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })
            ->latest('published_at')
            ->limit(4)
            ->get()
            ->map(fn (Article $article): array => [
                'id' => 'article:'.$article->id,
                'type' => 'article',
                'title' => $article->title,
                'summary' => $this->summary($article->excerpt ?: $article->body),
                'location' => null,
                'url' => route('articles.show', $article),
                'meta' => [
                    'published' => $article->published_at?->format('Y-m-d'),
                    'author' => $article->author?->name,
                    'categories' => $article->categories->pluck('name')->values()->all(),
                ],
            ]);
    }

    private function voucherSources(array $terms): Collection
    {
        return Voucher::with('listing')
            ->active()
            ->whereHas('listing', fn (Builder $listing) => $listing->published())
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'description', 'terms'], $terms);
                $query->orWhereHas('listing', fn (Builder $listing) => $this->applyTermSearch($listing, ['title', 'city', 'region'], $terms));
            })
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Voucher $voucher): array => [
                'id' => 'voucher:'.$voucher->id,
                'type' => 'voucher',
                'title' => $voucher->title,
                'summary' => $this->summary($voucher->description ?: $voucher->terms),
                'location' => $this->location([$voucher->listing?->city, $voucher->listing?->region]),
                'url' => route('vouchers.show', [$voucher->listing, $voucher]),
                'meta' => [
                    'business' => $voucher->listing?->title,
                    'value' => $voucher->formattedValue(),
                    'ends_at' => $voucher->end_at?->format('Y-m-d'),
                ],
            ]);
    }

    private function classifiedSources(array $terms): Collection
    {
        return Classified::query()
            ->where('status', Classified::STATUS_PUBLISHED)
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'description', 'city', 'region'], $terms);
            })
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Classified $classified): array => [
                'id' => 'classified:'.$classified->id,
                'type' => 'classified',
                'title' => $classified->title,
                'summary' => $this->summary($classified->description),
                'location' => $this->location([$classified->city, $classified->region]),
                'url' => route('classifieds.show', $classified),
                'meta' => [
                    'price' => $classified->contact_for_price ? 'Contact for price' : ($classified->price !== null ? $classified->currency.' '.number_format((float) $classified->price, 2) : null),
                ],
            ]);
    }

    private function faultSources(array $terms): Collection
    {
        return CivicFaultReport::query()
            ->where('is_approved', true)
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['category', 'severity', 'status', 'address_label', 'description'], $terms);
            })
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (CivicFaultReport $fault): array => [
                'id' => 'fault:'.$fault->id,
                'type' => 'fault',
                'title' => (CivicFaultReport::categories()[$fault->category] ?? Str::headline($fault->category)).' near '.$fault->address_label,
                'summary' => $this->summary($fault->description),
                'location' => $fault->address_label,
                'url' => route('faults.index', ['category' => $fault->category, 'status' => $fault->status]),
                'meta' => [
                    'status' => CivicFaultReport::statuses()[$fault->status] ?? $fault->status,
                    'severity' => CivicFaultReport::severities()[$fault->severity] ?? $fault->severity,
                    'reported' => $fault->created_at?->format('Y-m-d'),
                ],
            ]);
    }

    private function fallbackAnswer(string $question, Collection $sources, string $reason): array
    {
        $topTypes = $sources
            ->groupBy('type')
            ->map(fn (Collection $items, string $type): string => $items->count().' '.$type.($items->count() === 1 ? '' : 's'))
            ->values()
            ->implode(', ');

        $first = $sources->first();
        $answer = $topTypes !== ''
            ? 'I found '.$topTypes.' on Life@ that may help. Start with '.$first['title'].'.'
            : 'I could not find a direct Life@ match yet.';

        return [
            'ok' => true,
            'source' => 'fallback',
            'answer' => $answer,
            'locale' => $this->detectLocale($answer, $question),
            'confidence' => $sources->isEmpty() ? 0 : 0.45,
            'sources' => $sources->take(8)->values()->all(),
            'follow_up_questions' => [
                'Search this phrase across Life@',
                'Try adding a town or category',
            ],
            'search_url' => route('search.index', ['q' => $question]),
            'message' => $reason,
        ];
    }

    private function applyTermSearch(Builder $query, array $columns, array $terms): void
    {
        foreach ($terms as $term) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        }
    }

    private function terms(string $question): array
    {
        $normalized = mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $question));
        $stopWords = [
            'and', 'the', 'for', 'with', 'near', 'from', 'that', 'this', 'what', 'where', 'when', 'who',
            'are', 'is', 'was', 'were', 'can', 'you', 'please', 'need', 'find', 'show', 'open', 'life',
            'wat', 'waar', 'wie', 'die', 'met', 'van', 'vir', 'het', 'kan', 'asseblief',
        ];

        return collect(preg_split('/\s+/', $normalized) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3 && ! in_array($term, $stopWords, true))
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    private function detectLocale(string $text, ?string $fallbackText = null): string
    {
        $combined = ' '.mb_strtolower($text.' '.($fallbackText ?? '')).' ';
        $afrikaansMarkers = [
            ' asseblief ', ' dankie ', ' waar ', ' wanneer ', ' hoekom ', ' hoeveel ', ' naby ',
            ' besigheid ', ' geleentheid ', ' fout ', ' krag ', ' pad ', ' slaggat ',
            ' vandag ', ' hierdie ', ' soek ', ' help my ', ' is daar ',
        ];

        foreach ($afrikaansMarkers as $marker) {
            if (str_contains($combined, $marker)) {
                return 'af';
            }
        }

        return 'en';
    }

    private function summary(?string $value): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value))), 260);
    }

    private function location(array $parts): ?string
    {
        $location = collect($parts)
            ->filter(fn ($part): bool => filled($part))
            ->unique()
            ->implode(', ');

        return $location !== '' ? $location : null;
    }
}
