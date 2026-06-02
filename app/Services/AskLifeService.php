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
use Carbon\CarbonImmutable;
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

    public function answer(string $question, ?User $user = null, array $history = [], array $context = []): array
    {
        $question = trim($question);
        $context = $this->normalizeContext($context);
        $intent = $this->detectIntent($question, $context, $user);
        $search = $this->searchContext($question, $context);

        if ($guided = $this->guidedAnswer($question, $context, $intent)) {
            return $guided;
        }

        $sources = $this->sourcesForQuestion($question, $user, $context, $intent, $search);

        if ($sources->isEmpty()) {
            $answer = $this->emptyAnswer($intent, $search);

            return [
                'ok' => true,
                'source' => 'fallback',
                'answer' => $answer,
                'locale' => $this->detectLocale($question, $answer),
                'confidence' => 0,
                'intent' => $intent,
                'search_context' => $this->publicSearchContext($search),
                'page_context' => $context,
                'sources' => [],
                'answer_actions' => $this->answerActions($intent, collect(), $question, $context, $search),
                'follow_up_questions' => [
                    $search['location'] ? 'Should I widen this beyond '.$search['location'].'?' : 'Which town should I search in?',
                    'Are you looking for a business, event, article, voucher, classified, fault report, or transport help?',
                ],
                'search_url' => route('search.index', ['q' => $question]),
            ];
        }

        if (! $this->gateway->configured()) {
            return $this->fallbackAnswer($question, $sources, 'AI provider is not configured yet.', $intent, $context, $search);
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
                    'conversation_history' => $this->formatHistory($history),
                    'detected_intent' => $intent,
                    'search_context' => $this->publicSearchContext($search),
                    'page_context' => $context,
                    'current_date' => CarbonImmutable::now($search['timezone'])->toDateString(),
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
                    'intent' => $intent,
                    'search_context' => $this->publicSearchContext($search),
                    'page_context' => $context,
                    'sources' => $this->sourceCards($rankedSources->take(8), $intent, $question, $context)->values()->all(),
                    'answer_actions' => $this->answerActions($intent, $rankedSources, $question, $context, $search),
                    'follow_up_questions' => collect(data_get($result, 'payload.follow_up_questions', []))->take(3)->values()->all(),
                    'generation_id' => data_get($result, 'generation.id'),
                    'search_url' => route('search.index', ['q' => $question]),
                ];
            }

            return $this->fallbackAnswer($question, $sources, $result['message'] ?? 'AI provider did not return a usable answer.', $intent, $context, $search);
        } catch (Throwable $exception) {
            return $this->fallbackAnswer($question, $sources, $exception->getMessage(), $intent, $context, $search);
        }
    }

    public function sourcesForQuestion(string $question, ?User $user = null, array $context = [], ?array $intent = null, ?array $search = null): Collection
    {
        $context = $this->normalizeContext($context);
        $intent ??= $this->detectIntent($question, $context, $user);
        $search ??= $this->searchContext($question, $context);
        $terms = $search['terms'];
        $dynamicSources = collect();

        if ($terms !== []) {
            foreach ($intent['source_types'] as $sourceType) {
                $dynamicSources = $dynamicSources->merge(match ($sourceType) {
                    'business' => $this->listingSources($terms, $user, $search),
                    'event' => $this->eventSources($terms, $user, $search),
                    'article' => $this->articleSources($terms, $user, $search),
                    'voucher' => $this->voucherSources($terms, $user, $search),
                    'classified' => $this->classifiedSources($terms, $user, $search),
                    'fault' => $this->faultSources($terms, $user, $search),
                    default => collect(),
                });
            }
        }

        return $this->rankSources($dynamicSources, $search, $intent)
            ->merge($this->pageContextSources($context, $intent))
            ->merge($this->platformGuideSources($question, $terms, $intent, $context))
            ->unique('id')
            ->take(18)
            ->values();
    }

    private function formatHistory(array $history): array
    {
        return collect($history)
            ->filter(fn (array $turn): bool => isset($turn['role'], $turn['content']) && filled($turn['content']))
            ->take(16)
            ->map(fn (array $turn): array => [
                'role' => $turn['role'] === 'user' ? 'user' : 'assistant',
                'content' => Str::limit(trim((string) $turn['content']), 500),
            ])
            ->values()
            ->all();
    }

    private function guidedAnswer(string $question, array $context, array $intent): ?array
    {
        if ($this->isBusinessOnboardingQuestion($question)) {
            return $this->businessOnboardingAnswer($question, $context, $intent);
        }

        return null;
    }

    private function businessOnboardingAnswer(string $question, array $context, array $intent): array
    {
        $answer = 'Yes. Start on Add Listing: create or log in to your account, enter your business name and town, choose a staff-assisted or self-service directory package, then continue to checkout. Once the listing is active, you can add events, adverts, push campaigns, and vouchers from your account.';
        $sources = collect([
            [
                'id' => 'guide:add-listing',
                'type' => 'start',
                'title' => 'Add your business listing',
                'summary' => 'Create a starter listing, choose a directory package, and continue to checkout.',
                'location' => null,
                'url' => route('add-listing.index'),
                'meta' => [
                    'action' => 'Start listing',
                ],
            ],
            [
                'id' => 'guide:advertise',
                'type' => 'packages',
                'title' => 'Compare advertising and directory packages',
                'summary' => 'See directory, event, advert, push, and voucher options.',
                'location' => null,
                'url' => route('advertise.index'),
                'meta' => [
                    'action' => 'Compare packages',
                ],
            ],
        ]);

        return [
            'ok' => true,
            'source' => 'guided',
            'answer' => $answer,
            'locale' => $this->detectLocale($answer, $question),
            'confidence' => 0.9,
            'intent' => $intent,
            'search_context' => $this->publicSearchContext($this->searchContext($question, $context)),
            'page_context' => $context,
            'sources' => $this->sourceCards($sources, $intent, $question, $context)->values()->all(),
            'answer_actions' => $this->answerActions($intent, $sources, $question, $context, $this->searchContext($question, $context)),
            'follow_up_questions' => [
                'Do you want staff-assisted setup or self-service?',
                'Which town is your business in?',
                'Do you also want adverts, events, push campaigns, or vouchers?',
            ],
            'search_url' => null,
        ];
    }

    private function isBusinessOnboardingQuestion(string $question): bool
    {
        $normalized = ' '.mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $question)).' ';

        $businessMarkers = [
            ' my business ', ' our business ', ' business ', ' company ', ' shop ', ' store ',
            ' besigheid ', ' onderneming ', ' winkel ',
        ];
        $actionMarkers = [
            ' add ', ' adding ', ' list ', ' listing ', ' register ', ' submit ', ' advertise ', ' onboard ',
            ' directory ', ' profile ', ' page ',
            ' voeg ', ' lys ', ' registreer ', ' adverteer ', ' gids ', ' profiel ',
        ];

        return collect($businessMarkers)->contains(fn (string $marker): bool => str_contains($normalized, $marker))
            && collect($actionMarkers)->contains(fn (string $marker): bool => str_contains($normalized, $marker));
    }

    private function listingSources(array $terms, ?User $user = null, array $search = []): Collection
    {
        $query = Listing::with('categories');

        // Public users: only published listings
        if (! $user) {
            $query->published();
        }
        // Signed-in listing owners can see their own work as well as public listings.
        elseif (! $user->hasRole('admin', 'editor', 'staff')) {
            $query->where(function (Builder $q) use ($user) {
                $q->published()
                    ->orWhere('user_id', $user->id);
            });
        }
        // Staff users: published + their own drafts/pending
        elseif ($user->hasRole('staff') && ! $user->hasRole('admin', 'editor')) {
            $query->where(function (Builder $q) use ($user) {
                $q->published()
                    ->orWhere('user_id', $user->id);
            });
        }
        // Admin/Editor: see everything (no scope applied)

        return $query
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'excerpt', 'description', 'city', 'region'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })
            ->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['city', 'region', 'country', 'address_line'], (string) $search['location']))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->limit(6)
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
                    'status' => $listing->status,
                    'featured' => $listing->is_featured,
                    'address' => $this->location([$listing->address_line, $listing->city, $listing->region]),
                ],
            ]);
    }

    private function eventSources(array $terms, ?User $user = null, array $search = []): Collection
    {
        $query = Event::with(['categories', 'listing']);

        // Public users: only published events
        if (! $user) {
            $query->published();
        }
        elseif (! $user->hasRole('admin', 'editor', 'staff')) {
            $query->where(function (Builder $q) use ($user) {
                $q->published()
                    ->orWhere('user_id', $user->id)
                    ->orWhereHas('listing', fn (Builder $listing) => $listing->where('user_id', $user->id));
            });
        }
        // Staff users: published + their own listings' events
        elseif ($user->hasRole('staff') && ! $user->hasRole('admin', 'editor')) {
            $query->where(function (Builder $q) use ($user) {
                $q->published()
                    ->orWhereHas('listing', fn (Builder $listing) => $listing->where('user_id', $user->id));
            });
        }
        // Admin/Editor: see everything

        return $query
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'excerpt', 'description', 'venue_name', 'city', 'region'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })
            ->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['venue_name', 'city', 'region', 'country', 'address_line'], (string) $search['location']))
            ->when(! empty($search['time_window']), function (Builder $query) use ($search): void {
                $query->whereBetween('start_at', [$search['time_window']['start'], $search['time_window']['end']]);
            })
            ->orderBy('start_at')
            ->limit(6)
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
                    'ends_at' => $event->end_at?->format('Y-m-d H:i'),
                    'categories' => $event->categories->pluck('name')->values()->all(),
                    'business' => $event->listing?->title,
                    'status' => $event->status,
                    'website' => $event->website_url,
                ],
            ]);
    }

    private function articleSources(array $terms, ?User $user = null, array $search = []): Collection
    {
        $query = Article::with(['author', 'categories']);

        // Public users: only published articles
        if (! $user || ! $user->hasRole('admin', 'editor', 'staff', 'writer')) {
            $query->published();
        }
        // Writers: published + their own drafts
        elseif ($user->hasRole('writer') && ! $user->hasRole('admin', 'editor', 'staff')) {
            $query->where(function (Builder $q) use ($user) {
                $q->published()
                    ->orWhere('author_id', $user->id);
            });
        }
        // Admin/Editor/Staff: see everything

        return $query
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'excerpt', 'body'], $terms);
                $query->orWhereHas('categories', fn (Builder $category) => $this->applyTermSearch($category, ['name'], $terms));
            })
            ->latest('published_at')
            ->limit(6)
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
                    'status' => $article->status,
                ],
            ]);
    }

    private function voucherSources(array $terms, ?User $user = null, array $search = []): Collection
    {
        $query = Voucher::with('listing');

        // Public users: only active vouchers with published listings
        if (! $user) {
            $query->active()
                ->whereHas('listing', fn (Builder $listing) => $listing->published());
        }
        elseif (! $user->hasRole('admin', 'editor', 'staff')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where(function (Builder $public) {
                    $public->active()
                        ->whereHas('listing', fn (Builder $listing) => $listing->published());
                })
                ->orWhereHas('listing', fn (Builder $listing) => $listing->where('user_id', $user->id));
            });
        }
        // Staff users: active vouchers for published listings + all vouchers for their own listings
        elseif ($user->hasRole('staff') && ! $user->hasRole('admin', 'editor')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where(function (Builder $public) {
                    $public->active()
                        ->whereHas('listing', fn (Builder $listing) => $listing->published());
                })
                ->orWhereHas('listing', fn (Builder $listing) => $listing->where('user_id', $user->id));
            });
        }
        // Admin/Editor: see everything (no scope)

        return $query
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'description', 'terms'], $terms);
                $query->orWhereHas('listing', fn (Builder $listing) => $this->applyTermSearch($listing, ['title', 'city', 'region'], $terms));
            })
            ->when(filled($search['location'] ?? null), fn (Builder $query) => $query->whereHas('listing', fn (Builder $listing) => $this->applyLocationSearch($listing, ['city', 'region', 'country', 'address_line'], (string) $search['location'])))
            ->latest('published_at')
            ->limit(5)
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
                    'status' => $voucher->status,
                    'remaining' => $voucher->remainingUses(),
                    'phone' => $voucher->listing?->phone,
                ],
            ]);
    }

    private function classifiedSources(array $terms, ?User $user = null, array $search = []): Collection
    {
        $query = Classified::query();

        // Public users: only published classifieds
        if (! $user) {
            $query->where('status', Classified::STATUS_PUBLISHED);
        }
        elseif (! $user->hasRole('admin', 'editor', 'staff')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('status', Classified::STATUS_PUBLISHED)
                    ->orWhere('user_id', $user->id);
            });
        }
        // Staff users: published + their own
        elseif ($user->hasRole('staff') && ! $user->hasRole('admin', 'editor')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('status', Classified::STATUS_PUBLISHED)
                    ->orWhere('user_id', $user->id);
            });
        }
        // Admin/Editor: see everything

        return $query
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['title', 'description', 'city', 'region'], $terms);
            })
            ->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['city', 'region', 'country'], (string) $search['location']))
            ->latest('published_at')
            ->limit(5)
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
                    'status' => $classified->status,
                ],
            ]);
    }

    private function faultSources(array $terms, ?User $user = null, array $search = []): Collection
    {
        $query = CivicFaultReport::query();

        // Public users: only approved faults
        if (! $user || ! $user->hasRole('admin', 'editor', 'councillor')) {
            $query->where('is_approved', true);
        }
        // Admin/Editor/Councillor: see all faults (including unapproved)

        return $query
            ->where(function (Builder $query) use ($terms) {
                $this->applyTermSearch($query, ['category', 'severity', 'status', 'address_label', 'description'], $terms);
            })
            ->when(filled($search['location'] ?? null), fn (Builder $query) => $this->applyLocationSearch($query, ['address_label'], (string) $search['location']))
            ->latest()
            ->limit(5)
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

    private function platformGuideSources(string $question, array $terms, array $intent, array $context): Collection
    {
        $normalized = mb_strtolower($question.' '.implode(' ', $terms));
        $broadHelp = trim($normalized) === ''
            || str_contains($normalized, 'help')
            || str_contains($normalized, 'what can')
            || str_contains($normalized, 'how can')
            || str_contains($normalized, 'jimmy')
            || str_contains($normalized, 'assist')
            || str_contains($normalized, 'ondersteun')
            || str_contains($normalized, 'help my');

        $guides = collect($this->platformGuides());

        if ($broadHelp) {
            return $guides->values();
        }

        $matched = $guides->filter(function (array $guide) use ($normalized): bool {
            foreach ($guide['keywords'] as $keyword) {
                if ($keyword !== '' && str_contains($normalized, $keyword)) {
                    return true;
                }
            }

            return false;
        });

        if (($intent['key'] ?? '') !== 'general') {
            $matched = $matched->merge($guides->whereIn('id', $this->guideIdsForIntent((string) $intent['key'])));
        }

        if (($context['page_type'] ?? '') !== '') {
            $matched = $matched->merge($guides->whereIn('id', $this->guideIdsForPageType((string) $context['page_type'])));
        }

        return $matched->isNotEmpty()
            ? $matched->unique('id')->values()
            : $guides->whereIn('id', ['guide:directory', 'guide:search', 'guide:contact'])->values();
    }

    private function platformGuides(): array
    {
        return [
            [
                'id' => 'guide:search',
                'type' => 'guide',
                'title' => 'Jimmy can help you navigate Life@',
                'summary' => 'Jimmy can help users find local businesses, articles, events, vouchers, classifieds, civic faults, transport help, and business onboarding steps. He should be honest when Life@ does not have a verified record yet.',
                'location' => 'Life@',
                'url' => route('search.index'),
                'meta' => [
                    'best_for' => 'General help, follow-up questions, and finding the right Life@ section.',
                ],
                'keywords' => ['help', 'assist', 'jimmy', 'what can', 'how can', 'find', 'search', 'soek'],
            ],
            [
                'id' => 'guide:directory',
                'type' => 'guide',
                'title' => 'Business directory',
                'summary' => 'The directory lists published local businesses. Users can search by business type, category, town, service, or business name.',
                'location' => 'Life@ Directory',
                'url' => route('directory.index'),
                'meta' => [
                    'best_for' => 'Finding businesses and services.',
                ],
                'keywords' => ['business', 'businesses', 'directory', 'service', 'services', 'shop', 'store', 'mechanic', 'plumber', 'restaurant', 'besigheid', 'winkel', 'gids'],
            ],
            [
                'id' => 'guide:business-owner',
                'type' => 'guide',
                'title' => 'Business owner tools',
                'summary' => 'Signed-in business owners can improve listings, add photos, create events, run adverts, send push campaigns, and publish vouchers from the account listing workspace.',
                'location' => 'Life@ Account',
                'url' => route('account.listings.index'),
                'meta' => [
                    'best_for' => 'Improving a business profile or deciding what to do next after adding a listing.',
                ],
                'keywords' => ['my listing', 'listing workspace', 'improve listing', 'business profile', 'photos', 'campaign', 'owner', 'dashboard'],
            ],
            [
                'id' => 'guide:add-listing',
                'type' => 'guide',
                'title' => 'Add a business listing',
                'summary' => 'Business owners can start a listing, choose staff-assisted or self-service onboarding, and unlock advertising options after the listing journey starts.',
                'location' => 'Life@ Onboarding',
                'url' => route('add-listing.index'),
                'meta' => [
                    'best_for' => 'Adding or registering a business on Life@.',
                ],
                'keywords' => ['add business', 'adding business', 'list business', 'listing', 'register business', 'onboard', 'advertise', 'adverteer', 'registreer'],
            ],
            [
                'id' => 'guide:advertise',
                'type' => 'guide',
                'title' => 'Advertising and packages',
                'summary' => 'Life@ advertising options include directory packages, event visibility, adverts, push campaigns, and vouchers. Business owners should choose based on whether they need discovery, reach, repeat visits, or a specific promotion.',
                'location' => 'Life@ Advertise',
                'url' => route('advertise.index'),
                'meta' => [
                    'best_for' => 'Choosing packages, campaigns, and promotional tools.',
                ],
                'keywords' => ['advertise', 'advertising', 'package', 'packages', 'campaign', 'promote', 'reach', 'boost', 'adverteer'],
            ],
            [
                'id' => 'guide:events',
                'type' => 'guide',
                'title' => 'Events',
                'summary' => 'Life@ events help users discover what is happening nearby. Event details should come from published event records.',
                'location' => 'Life@ Events',
                'url' => route('events.index'),
                'meta' => [
                    'best_for' => 'Finding local events and dates.',
                ],
                'keywords' => ['event', 'events', 'weekend', 'festival', 'market', 'show', 'concert', 'geleentheid', 'gebeure'],
            ],
            [
                'id' => 'guide:articles',
                'type' => 'guide',
                'title' => 'Articles and local updates',
                'summary' => 'Life@ articles cover local stories and community updates. Jimmy should summarize only published article records supplied to him.',
                'location' => 'Life@ Articles',
                'url' => route('articles.index'),
                'meta' => [
                    'best_for' => 'Local news, updates, and explainers.',
                ],
                'keywords' => ['article', 'articles', 'news', 'story', 'stories', 'update', 'load shedding', 'water', 'nuus'],
            ],
            [
                'id' => 'guide:vouchers',
                'type' => 'guide',
                'title' => 'Vouchers and offers',
                'summary' => 'Life@ vouchers show active offers from listed businesses. Voucher value and terms must come from published voucher records.',
                'location' => 'Life@ Vouchers',
                'url' => route('vouchers.index'),
                'meta' => [
                    'best_for' => 'Deals, specials, discounts, and local offers.',
                ],
                'keywords' => ['voucher', 'vouchers', 'deal', 'special', 'discount', 'offer', 'coupon', 'koepon', 'aanbod'],
            ],
            [
                'id' => 'guide:classifieds',
                'type' => 'guide',
                'title' => 'Classifieds',
                'summary' => 'Life@ classifieds help users find or submit local items and community listings. Prices and availability must come from published classified records.',
                'location' => 'Life@ Classifieds',
                'url' => route('classifieds.index'),
                'meta' => [
                    'best_for' => 'Buying, selling, and browsing local classified items.',
                ],
                'keywords' => ['classified', 'classifieds', 'sell', 'buy', 'sale', 'bakkie', 'car', 'item', 'marketplace', 'koop', 'verkoop'],
            ],
            [
                'id' => 'guide:faults',
                'type' => 'guide',
                'title' => 'Civic fault reports',
                'summary' => 'Life@ faults help users view and report approved civic issues such as water leaks, potholes, streetlights, dumping, and electricity problems.',
                'location' => 'Life@ Faults',
                'url' => route('faults.index'),
                'meta' => [
                    'best_for' => 'Viewing or reporting local civic issues.',
                ],
                'keywords' => ['fault', 'faults', 'pothole', 'water leak', 'burst pipe', 'streetlight', 'dumping', 'electricity', 'report', 'fout', 'slaggat'],
            ],
            [
                'id' => 'guide:transport',
                'type' => 'guide',
                'title' => 'Transport help',
                'summary' => 'Life@ transport can guide users toward ride, taxi, delivery, or moving-request workflows when those services are available.',
                'location' => 'Life@ Transport',
                'url' => route('transport.index'),
                'meta' => [
                    'best_for' => 'Taxi, delivery, bakkie, parcel, and moving help.',
                ],
                'keywords' => ['taxi', 'transport', 'delivery', 'parcel', 'ride', 'bakkie', 'move', 'moving', 'aflewering', 'vervoer'],
            ],
            [
                'id' => 'guide:contact',
                'type' => 'guide',
                'title' => 'Contact Life@',
                'summary' => 'When a user needs human help, Life@ contact is the safest next step. Jimmy should not pretend to make official decisions or access private admin records.',
                'location' => 'Life@ Support',
                'url' => route('contact.index'),
                'meta' => [
                    'best_for' => 'Human support, uncertainty, corrections, or private account help.',
                ],
                'keywords' => ['contact', 'support', 'person', 'human', 'helpdesk', 'private', 'account', 'payment', 'billing', 'ondersteuning'],
            ],
        ];
    }

    private function fallbackAnswer(string $question, Collection $sources, string $reason, array $intent, array $context, array $search): array
    {
        if ($sources->isNotEmpty() && $sources->every(fn (array $source): bool => ($source['type'] ?? null) === 'guide')) {
            return [
                'ok' => true,
                'source' => 'fallback',
                'answer' => 'I can help you work out where to go on Life@: businesses, events, local articles, vouchers, classifieds, civic fault reports, transport help, and business onboarding. I will be honest when I do not have a verified Life@ record yet, and I will point you to the safest next step.',
                'locale' => $this->detectLocale($question),
                'confidence' => 0.35,
                'intent' => $intent,
                'search_context' => $this->publicSearchContext($search),
                'page_context' => $context,
                'sources' => $this->sourceCards($sources->take(8), $intent, $question, $context)->values()->all(),
                'answer_actions' => $this->answerActions($intent, $sources, $question, $context, $search),
                'follow_up_questions' => [
                    'What town should I focus on?',
                    'Are you looking for a business, event, article, voucher, classified, fault report, or transport help?',
                ],
                'search_url' => route('search.index', ['q' => $question]),
                'message' => $reason,
            ];
        }

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
            'intent' => $intent,
            'search_context' => $this->publicSearchContext($search),
            'page_context' => $context,
            'sources' => $this->sourceCards($sources->take(8), $intent, $question, $context)->values()->all(),
            'answer_actions' => $this->answerActions($intent, $sources, $question, $context, $search),
            'follow_up_questions' => [
                $search['location'] ? 'Show me more near '.$search['location'] : 'Try adding a town or category',
                'Search this phrase across Life@',
            ],
            'search_url' => route('search.index', ['q' => $question]),
            'message' => $reason,
        ];
    }

    private function normalizeContext(array $context): array
    {
        $allowed = [
            'page_type',
            'page_title',
            'page_heading',
            'page_url',
            'path',
            'timezone',
            'local_time',
            'locale',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = Str::limit($value, $key === 'page_url' ? 2048 : 220, '');
            }
        }

        $clean['page_type'] ??= $this->inferPageType((string) ($clean['path'] ?? ''), (string) ($clean['page_url'] ?? ''));
        $clean['timezone'] = $this->validTimezone((string) ($clean['timezone'] ?? 'Africa/Johannesburg'));

        return $clean;
    }

    private function inferPageType(string $path, string $url): string
    {
        $target = mb_strtolower($path.' '.$url);

        return match (true) {
            str_contains($target, '/account/listings') => 'account_listing_workspace',
            str_contains($target, '/account/advertising') => 'account_advertising',
            str_contains($target, '/account') => 'account',
            str_contains($target, '/directory/') => 'business_detail',
            str_contains($target, '/directory') => 'directory',
            str_contains($target, '/events/') => 'event_detail',
            str_contains($target, '/events') => 'events',
            str_contains($target, '/articles/') => 'article_detail',
            str_contains($target, '/articles') => 'articles',
            str_contains($target, '/vouchers') => 'vouchers',
            str_contains($target, '/classifieds') || str_contains($target, '/my-classifieds') => 'classifieds',
            str_contains($target, '/faults') => 'faults',
            str_contains($target, '/transport') => 'transport',
            str_contains($target, '/advertise') => 'advertise',
            str_contains($target, '/add-listing') => 'add_listing',
            str_contains($target, '/checkout') || str_contains($target, '/basket') => 'checkout',
            default => 'general',
        };
    }

    private function detectIntent(string $question, array $context, ?User $user): array
    {
        $normalized = ' '.mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $question.' '.($context['page_heading'] ?? '').' '.($context['page_type'] ?? ''))).' ';
        $pageType = (string) ($context['page_type'] ?? 'general');

        $definitions = [
            'business_owner' => [
                'label' => 'Business owner help',
                'source_types' => ['business', 'voucher', 'event', 'article', 'classified', 'fault'],
                'markers' => [' my listing ', ' improve listing ', ' business profile ', ' add business ', ' advertise ', ' campaign ', ' push campaign ', ' voucher idea ', ' business owner ', ' listing workspace ', ' package ', ' checkout '],
            ],
            'business_search' => [
                'label' => 'Find a business',
                'source_types' => ['business', 'voucher', 'event', 'article', 'classified', 'fault'],
                'markers' => [' mechanic ', ' plumber ', ' restaurant ', ' coffee ', ' doctor ', ' dentist ', ' shop ', ' store ', ' business ', ' service ', ' services ', ' tyre ', ' tire ', ' repair ', ' salon ', ' electrician ', ' builder ', ' contractor '],
            ],
            'event_discovery' => [
                'label' => 'Find events',
                'source_types' => ['event', 'business', 'voucher', 'article', 'classified', 'fault'],
                'markers' => [' event ', ' events ', ' weekend ', ' today ', ' tonight ', ' tomorrow ', ' festival ', ' market ', ' concert ', ' show ', ' geleentheid ', ' gebeure '],
            ],
            'voucher_discovery' => [
                'label' => 'Find offers',
                'source_types' => ['voucher', 'business', 'event', 'article', 'classified', 'fault'],
                'markers' => [' voucher ', ' deal ', ' special ', ' discount ', ' offer ', ' coupon ', ' promo ', ' koepon ', ' aanbod '],
            ],
            'classified_discovery' => [
                'label' => 'Find classifieds',
                'source_types' => ['classified', 'business', 'voucher', 'event', 'article', 'fault'],
                'markers' => [' classified ', ' classifieds ', ' for sale ', ' buy ', ' sell ', ' marketplace ', ' bakkie ', ' car ', ' furniture ', ' koop ', ' verkoop '],
            ],
            'fault_reporting' => [
                'label' => 'Civic fault help',
                'source_types' => ['fault', 'article', 'business', 'event', 'voucher', 'classified'],
                'markers' => [' fault ', ' pothole ', ' water leak ', ' burst pipe ', ' streetlight ', ' dumping ', ' electricity ', ' outage ', ' report ', ' slaggat ', ' fout ', ' krag '],
            ],
            'transport_help' => [
                'label' => 'Transport help',
                'source_types' => ['business', 'classified', 'article', 'event', 'voucher', 'fault'],
                'markers' => [' taxi ', ' transport ', ' ride ', ' delivery ', ' parcel ', ' move ', ' moving ', ' bakkie delivery ', ' vervoer ', ' aflewering '],
            ],
            'article_lookup' => [
                'label' => 'Local articles',
                'source_types' => ['article', 'event', 'business', 'fault', 'voucher', 'classified'],
                'markers' => [' article ', ' news ', ' story ', ' stories ', ' update ', ' latest ', ' explain ', ' nuus '],
            ],
            'support' => [
                'label' => 'Human support',
                'source_types' => ['article', 'business', 'event', 'voucher', 'classified', 'fault'],
                'markers' => [' contact ', ' support ', ' helpdesk ', ' human ', ' account ', ' payment ', ' billing ', ' invoice ', ' refund ', ' person '],
            ],
        ];

        if (in_array($pageType, ['account_listing_workspace', 'account_advertising', 'advertise', 'add_listing', 'checkout'], true)) {
            $definitions['business_owner']['markers'][] = ' '.$pageType.' ';
        }

        foreach ($definitions as $key => $definition) {
            if ($this->containsAny($normalized, $definition['markers'])) {
                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'source_types' => $definition['source_types'],
                    'confidence' => 0.82,
                    'user_role' => $this->userRoleLabel($user),
                    'page_type' => $pageType,
                ];
            }
        }

        $sourceTypes = match ($pageType) {
            'business_detail', 'directory' => ['business', 'voucher', 'event', 'article', 'classified', 'fault'],
            'event_detail', 'events' => ['event', 'business', 'voucher', 'article', 'classified', 'fault'],
            'article_detail', 'articles' => ['article', 'event', 'business', 'fault', 'voucher', 'classified'],
            'vouchers' => ['voucher', 'business', 'event', 'article', 'classified', 'fault'],
            'classifieds' => ['classified', 'business', 'voucher', 'event', 'article', 'fault'],
            'faults' => ['fault', 'article', 'business', 'event', 'voucher', 'classified'],
            'transport' => ['business', 'classified', 'article', 'event', 'voucher', 'fault'],
            default => ['business', 'event', 'voucher', 'article', 'classified', 'fault'],
        };

        return [
            'key' => $pageType === 'general' ? 'general' : 'page_help',
            'label' => $pageType === 'general' ? 'General Life@ help' : 'Current page help',
            'source_types' => $sourceTypes,
            'confidence' => $pageType === 'general' ? 0.4 : 0.68,
            'user_role' => $this->userRoleLabel($user),
            'page_type' => $pageType,
        ];
    }

    private function searchContext(string $question, array $context): array
    {
        $baseTerms = $this->terms($question.' '.($context['page_heading'] ?? ''));
        $location = $this->detectLocation($question.' '.($context['page_heading'] ?? ''));
        $timezone = $this->validTimezone((string) ($context['timezone'] ?? 'Africa/Johannesburg'));
        $timeWindow = $this->detectTimeWindow($question, $timezone);
        $expanded = $this->expandedTerms($baseTerms);

        if ($location) {
            $expanded[] = mb_strtolower($location);
        }

        return [
            'base_terms' => $baseTerms,
            'terms' => collect($expanded)->filter()->unique()->take(18)->values()->all(),
            'location' => $location,
            'time_window' => $timeWindow,
            'timezone' => $timezone,
            'near_me' => str_contains(' '.mb_strtolower($question).' ', ' near me ') || str_contains(' '.mb_strtolower($question).' ', ' naby my '),
        ];
    }

    private function publicSearchContext(array $search): array
    {
        return [
            'base_terms' => $search['base_terms'] ?? [],
            'location' => $search['location'] ?? null,
            'time_window' => $search['time_window'] ?? null,
            'timezone' => $search['timezone'] ?? 'Africa/Johannesburg',
            'near_me' => (bool) ($search['near_me'] ?? false),
        ];
    }

    private function expandedTerms(array $baseTerms): array
    {
        $map = [
            'mechanic' => ['auto', 'vehicle', 'car', 'repair', 'garage', 'workshop', 'service'],
            'tyre' => ['tire', 'wheel', 'puncture', 'alignment', 'mechanic', 'auto'],
            'tire' => ['tyre', 'wheel', 'puncture', 'alignment', 'mechanic', 'auto'],
            'restaurant' => ['food', 'eat', 'dinner', 'lunch', 'coffee', 'takeaway'],
            'coffee' => ['cafe', 'restaurant', 'breakfast', 'food'],
            'doctor' => ['medical', 'clinic', 'health', 'gp'],
            'dentist' => ['dental', 'teeth', 'medical', 'health'],
            'plumber' => ['water', 'pipe', 'leak', 'repair'],
            'electrician' => ['electrical', 'power', 'wiring', 'repair'],
            'fault' => ['report', 'pothole', 'water', 'electricity', 'streetlight', 'dumping'],
            'pothole' => ['road', 'fault', 'report', 'slaggat'],
            'water' => ['leak', 'pipe', 'fault', 'municipal'],
            'event' => ['events', 'weekend', 'market', 'festival', 'show'],
            'voucher' => ['deal', 'special', 'discount', 'offer'],
            'classified' => ['sale', 'buy', 'sell', 'marketplace'],
            'transport' => ['taxi', 'ride', 'delivery', 'parcel', 'moving'],
        ];

        $expanded = $baseTerms;

        foreach ($baseTerms as $term) {
            foreach ($map as $key => $synonyms) {
                if ($term === $key || in_array($term, $synonyms, true)) {
                    $expanded = array_merge($expanded, [$key], $synonyms);
                }
            }
        }

        return $expanded;
    }

    private function detectLocation(string $text): ?string
    {
        $normalized = ' '.mb_strtolower((string) preg_replace('/[^\pL\pN]+/u', ' ', $text)).' ';

        foreach ($this->knownLocations() as $location) {
            if (str_contains($normalized, ' '.mb_strtolower($location).' ')) {
                return $location;
            }
        }

        return null;
    }

    private function knownLocations(): array
    {
        return [
            'Bethlehem',
            'Harrismith',
            'Clarens',
            'Reitz',
            'Kestell',
            'Fouriesburg',
            'Ficksburg',
            'Ladybrand',
            'Phuthaditjhaba',
            'Qwaqwa',
            'Warden',
            'Frankfort',
            'Vrede',
            'Lindley',
            'Senekal',
            'Rosendal',
            'Arlington',
            'Paul Roux',
            'Golden Gate',
            'Eastern Free State',
            'Freestate',
            'Free State',
        ];
    }

    private function detectTimeWindow(string $question, string $timezone): ?array
    {
        $normalized = ' '.mb_strtolower($question).' ';
        $now = CarbonImmutable::now($timezone);

        if ($this->containsAny($normalized, [' today ', ' vandag '])) {
            return [
                'label' => 'today',
                'start' => $now->startOfDay()->toDateTimeString(),
                'end' => $now->endOfDay()->toDateTimeString(),
            ];
        }

        if ($this->containsAny($normalized, [' tomorrow ', ' more '])) {
            $day = $now->addDay();

            return [
                'label' => 'tomorrow',
                'start' => $day->startOfDay()->toDateTimeString(),
                'end' => $day->endOfDay()->toDateTimeString(),
            ];
        }

        if ($this->containsAny($normalized, [' tonight ', ' vanaand '])) {
            return [
                'label' => 'tonight',
                'start' => $now->toDateTimeString(),
                'end' => $now->endOfDay()->toDateTimeString(),
            ];
        }

        if ($this->containsAny($normalized, [' weekend ', ' this weekend ', ' naweek '])) {
            $saturday = $now->isSaturday()
                ? $now
                : ($now->isSunday() ? $now->subDay() : $now->next(CarbonImmutable::SATURDAY));

            return [
                'label' => 'this weekend',
                'start' => $saturday->startOfDay()->toDateTimeString(),
                'end' => $saturday->addDay()->endOfDay()->toDateTimeString(),
            ];
        }

        if ($this->containsAny($normalized, [' next week ', ' volgende week '])) {
            $monday = $now->next(CarbonImmutable::MONDAY);

            return [
                'label' => 'next week',
                'start' => $monday->startOfDay()->toDateTimeString(),
                'end' => $monday->endOfWeek()->endOfDay()->toDateTimeString(),
            ];
        }

        return null;
    }

    private function pageContextSources(array $context, array $intent): Collection
    {
        if (($context['page_type'] ?? 'general') === 'general') {
            return collect();
        }

        $title = $context['page_heading'] ?? $context['page_title'] ?? 'Current Life@ page';
        $summary = 'The user is currently viewing this Life@ page. Use it to answer follow-up questions about what they are looking at, while staying honest about facts not present in the page context.';

        if (($context['page_type'] ?? '') === 'account_listing_workspace') {
            $summary = 'The user is in a listing workspace. Help them improve the listing, add photos, create events, run adverts, prepare push campaigns, publish vouchers, or find human support.';
        }

        return collect([
            [
                'id' => 'page:current',
                'type' => 'page',
                'title' => $title,
                'summary' => $summary,
                'location' => 'Current page',
                'url' => $context['page_url'] ?? null,
                'meta' => [
                    'page_type' => $context['page_type'] ?? 'general',
                    'intent' => $intent['label'] ?? 'Current page help',
                ],
            ],
        ]);
    }

    private function guideIdsForIntent(string $intent): array
    {
        return match ($intent) {
            'business_owner' => ['guide:business-owner', 'guide:add-listing', 'guide:advertise', 'guide:contact'],
            'business_search' => ['guide:directory', 'guide:search'],
            'event_discovery' => ['guide:events', 'guide:search'],
            'voucher_discovery' => ['guide:vouchers', 'guide:directory'],
            'classified_discovery' => ['guide:classifieds'],
            'fault_reporting' => ['guide:faults', 'guide:contact'],
            'transport_help' => ['guide:transport', 'guide:contact'],
            'article_lookup' => ['guide:articles'],
            'support' => ['guide:contact'],
            default => [],
        };
    }

    private function guideIdsForPageType(string $pageType): array
    {
        return match ($pageType) {
            'account_listing_workspace', 'account_advertising', 'advertise', 'add_listing', 'checkout' => ['guide:business-owner', 'guide:add-listing', 'guide:advertise'],
            'business_detail', 'directory' => ['guide:directory'],
            'event_detail', 'events' => ['guide:events'],
            'article_detail', 'articles' => ['guide:articles'],
            'vouchers' => ['guide:vouchers'],
            'classifieds' => ['guide:classifieds'],
            'faults' => ['guide:faults'],
            'transport' => ['guide:transport'],
            default => [],
        };
    }

    private function rankSources(Collection $sources, array $search, array $intent): Collection
    {
        $baseTerms = $search['base_terms'] ?? [];
        $expandedTerms = $search['terms'] ?? [];
        $sourceTypes = array_values($intent['source_types'] ?? []);

        return $sources
            ->map(function (array $source) use ($baseTerms, $expandedTerms, $sourceTypes, $search): array {
                $haystack = mb_strtolower(($source['title'] ?? '').' '.($source['summary'] ?? '').' '.($source['location'] ?? '').' '.json_encode($source['meta'] ?? []));
                $score = 0;

                foreach ($baseTerms as $term) {
                    if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                        $score += 8;
                    }
                }

                foreach ($expandedTerms as $term) {
                    if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                        $score += 3;
                    }
                }

                $typeIndex = array_search($source['type'] ?? '', $sourceTypes, true);
                if ($typeIndex !== false) {
                    $score += max(4, 18 - ($typeIndex * 2));
                }

                if (filled($search['location'] ?? null) && str_contains($haystack, mb_strtolower((string) $search['location']))) {
                    $score += 10;
                }

                if (($source['type'] ?? '') === 'business' && (bool) data_get($source, 'meta.featured')) {
                    $score += 2;
                }

                $source['relevance_score'] = $score;

                return $source;
            })
            ->sortByDesc('relevance_score')
            ->values();
    }

    private function sourceCards(Collection $sources, array $intent, string $question, array $context): Collection
    {
        return $sources->map(function (array $source) use ($intent, $question, $context): array {
            unset($source['keywords']);

            $source['label'] = $this->sourceLabel((string) ($source['type'] ?? 'source'));
            $source['meta'] = collect($source['meta'] ?? [])
                ->reject(fn ($value): bool => $value === null || $value === '' || $value === [])
                ->all();
            $source['actions'] = $this->sourceActions($source, $intent, $question, $context);

            return $source;
        });
    }

    private function sourceActions(array $source, array $intent, string $question, array $context): array
    {
        $actions = [];

        if (filled($source['url'] ?? null)) {
            $actions[] = $this->action('View', (string) $source['url'], 'primary');
        }

        if (filled(data_get($source, 'meta.phone'))) {
            $phone = preg_replace('/[^\d+]/', '', (string) data_get($source, 'meta.phone'));
            if ($phone !== '') {
                $actions[] = $this->action('Call', 'tel:'.$phone, 'phone');
            }
        }

        if (filled(data_get($source, 'meta.website'))) {
            $actions[] = $this->action('Website', (string) data_get($source, 'meta.website'), 'external', true);
        }

        $mapQuery = $this->mapQueryForSource($source);
        if ($mapQuery !== null) {
            $actions[] = $this->action('Directions', $this->mapsUrl($mapQuery), 'directions', true);
        }

        if (($source['type'] ?? '') === 'fault') {
            $actions[] = $this->action('Report fault', route('faults.report.create'), 'secondary');
        }

        if (($source['id'] ?? '') === 'guide:business-owner') {
            $actions[] = $this->action('Open listings', route('account.listings.index'), 'secondary');
        }

        return collect($actions)
            ->unique(fn (array $action): string => $action['label'].'|'.$action['url'])
            ->take(4)
            ->values()
            ->all();
    }

    private function answerActions(array $intent, Collection $sources, string $question, array $context, array $search): array
    {
        $actions = [];
        $first = $sources->first();

        if (is_array($first) && filled($first['url'] ?? null)) {
            $actions[] = $this->action('Open best match', (string) $first['url'], 'primary');
        }

        $actions[] = $this->action('Full search', route('search.index', array_filter([
            'q' => $question,
            'loc' => $search['location'] ?? null,
        ])), 'search');

        foreach (match ($intent['key'] ?? 'general') {
            'business_owner' => [
                $this->action('My listings', route('account.listings.index'), 'secondary'),
                $this->action('Advertise', route('advertise.index'), 'secondary'),
            ],
            'event_discovery' => [
                $this->action('Events', route('events.index', array_filter(['q' => $question, 'location' => $search['location'] ?? null])), 'secondary'),
            ],
            'voucher_discovery' => [
                $this->action('Vouchers', route('vouchers.index'), 'secondary'),
            ],
            'classified_discovery' => [
                $this->action('Classifieds', route('classifieds.index'), 'secondary'),
                $this->action('Post classified', route('classifieds.manage.create'), 'secondary'),
            ],
            'fault_reporting' => [
                $this->action('Fault map', route('faults.index'), 'secondary'),
                $this->action('Report fault', route('faults.report.create'), 'secondary'),
            ],
            'transport_help' => [
                $this->action('Transport', route('transport.index'), 'secondary'),
            ],
            'support' => [
                $this->action('Contact Life@', route('contact.index'), 'secondary'),
            ],
            default => [],
        } as $action) {
            $actions[] = $action;
        }

        if (($context['page_type'] ?? '') === 'account_listing_workspace') {
            $actions[] = $this->action('Listing workspace', route('account.listings.index'), 'secondary');
        }

        return collect($actions)
            ->unique(fn (array $action): string => $action['label'].'|'.$action['url'])
            ->take(4)
            ->values()
            ->all();
    }

    private function action(string $label, string $url, string $kind = 'link', bool $external = false): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'kind' => $kind,
            'external' => $external,
        ];
    }

    private function sourceLabel(string $type): string
    {
        return match ($type) {
            'business' => 'Business',
            'event' => 'Event',
            'article' => 'Article',
            'voucher' => 'Voucher',
            'classified' => 'Classified',
            'fault' => 'Fault',
            'guide' => 'Guide',
            'page' => 'Current page',
            default => Str::headline($type),
        };
    }

    private function mapQueryForSource(array $source): ?string
    {
        $location = (string) ($source['location'] ?? data_get($source, 'meta.address', ''));
        if ($location === '' || in_array(($source['type'] ?? ''), ['article', 'guide', 'page'], true)) {
            return null;
        }

        return trim(($source['title'] ?? '').' '.$location);
    }

    private function mapsUrl(string $query): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);
    }

    private function emptyAnswer(array $intent, array $search): string
    {
        if (($search['near_me'] ?? false) && ! $search['location']) {
            return 'I can help with that, but I need the town first because Life@ does not have your precise location in this chat. Tell me the town or area and I will narrow it down.';
        }

        return match ($intent['key'] ?? 'general') {
            'event_discovery' => 'I could not find a matching Life@ event for that time or place yet. Try a town, venue, or event type and I will check again.',
            'voucher_discovery' => 'I could not find a matching active Life@ voucher yet. Try the business name, town, or kind of special you want.',
            'fault_reporting' => 'I could not find a matching approved fault report yet. You can open the fault map or report a new fault with a location and photo.',
            'business_owner' => 'I could not find a matching listing workspace item yet. Tell me which business or task you mean, and I can point you to the next account action.',
            default => 'I could not find a direct Life@ match yet. Try a more specific town, business type, event name, article topic, voucher, classified item, or fault category.',
        };
    }

    private function applyLocationSearch(Builder $query, array $columns, string $location): void
    {
        $query->where(function (Builder $inner) use ($columns, $location): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', '%'.$location.'%');
            }
        });
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

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function validTimezone(string $timezone): string
    {
        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : 'Africa/Johannesburg';
    }

    private function userRoleLabel(?User $user): string
    {
        if (! $user) {
            return 'public';
        }

        foreach (['admin', 'editor', 'staff', 'writer', 'councillor', 'member'] as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return 'signed_in';
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
