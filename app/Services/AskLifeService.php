<?php

namespace App\Services;

use App\Ai\Knowledge\KnowledgeRetriever;
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
        private readonly KnowledgeRetriever $knowledge,
    ) {
    }

    public function answer(string $question, ?User $user = null, array $history = [], array $context = []): array
    {
        $question = trim($question);
        $context = $this->normalizeContext($context);
        $targetLocale = $this->targetLocale($question, $user, $context);
        $context['locale'] = $targetLocale;
        $intent = $this->detectIntent($question, $context, $user);
        $search = $this->searchContext($question, $context);

        if ($guided = $this->guidedAnswer($question, $user, $context, $intent, $search, $targetLocale)) {
            return $guided;
        }

        $sources = $this->sourcesForQuestion($question, $user, $context, $intent, $search);

        if ($sources->isEmpty()) {
            $answer = $this->emptyAnswer($intent, $search, $targetLocale);

            return [
                'ok' => true,
                'source' => 'fallback',
                'answer' => $answer,
                'locale' => $targetLocale,
                'confidence' => 0,
                'intent' => $intent,
                'search_context' => $this->publicSearchContext($search),
                'page_context' => $context,
                'sources' => [],
                'answer_actions' => $this->answerActions($intent, collect(), $question, $context, $search, $targetLocale),
                'follow_up_questions' => [
                    $search['location']
                        ? $this->t('follow.widen_location', $targetLocale, ['location' => (string) $search['location']])
                        : $this->t('follow.which_town', $targetLocale),
                    $this->t('follow.what_type', $targetLocale),
                ],
                'search_url' => route('search.index', ['q' => $question]),
            ];
        }

        if (config('ai_platform.public_chat.emergency_stop')) {
            return $this->fallbackAnswer($question, $sources, 'Ask Life generation is temporarily disabled.', $intent, $context, $search, $targetLocale);
        }

        if (! $this->gateway->configured()) {
            return $this->fallbackAnswer($question, $sources, 'AI provider is not configured yet.', $intent, $context, $search, $targetLocale);
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
                    'target_locale' => $targetLocale,
                    'target_language' => $this->localeName($targetLocale),
                    'language_instruction' => $this->languageInstruction($targetLocale),
                    'current_date' => CarbonImmutable::now($search['timezone'])->toDateString(),
                ],
                null,
                $user,
                $targetLocale,
            );

            if (($result['ok'] ?? false) && filled(data_get($result, 'payload.answer'))) {
                $usedIds = collect(data_get($result, 'payload.source_ids', []))
                    ->filter(fn ($id) => is_string($id) && $id !== '')
                    ->values();
                $availableIds = $sources->pluck('id');
                $unsupportedIds = $usedIds->diff($availableIds);

                if ($usedIds->isEmpty() || $unsupportedIds->isNotEmpty()) {
                    return $this->fallbackAnswer(
                        $question,
                        $sources,
                        'The generated answer did not provide valid supporting Life@ sources.',
                        $intent,
                        $context,
                        $search,
                        $targetLocale,
                    );
                }

                $rankedSources = $sources
                    ->sortBy(fn (array $source) => $usedIds->search($source['id']) === false ? 999 : $usedIds->search($source['id']))
                    ->values();

                return [
                    'ok' => true,
                    'source' => 'ai',
                    'answer' => (string) data_get($result, 'payload.answer'),
                    'locale' => $targetLocale,
                    'confidence' => (float) data_get($result, 'payload.confidence', 0.65),
                    'intent' => $intent,
                    'search_context' => $this->publicSearchContext($search),
                    'page_context' => $context,
                    'sources' => $this->sourceCards($rankedSources->take(8), $intent, $question, $context, $targetLocale)->values()->all(),
                    'answer_actions' => $this->answerActions($intent, $rankedSources, $question, $context, $search, $targetLocale),
                    'follow_up_questions' => collect(data_get($result, 'payload.follow_up_questions', []))->take(3)->values()->all(),
                    'generation_id' => data_get($result, 'generation.id'),
                    'search_url' => route('search.index', ['q' => $question]),
                ];
            }

            return $this->fallbackAnswer($question, $sources, $result['message'] ?? 'AI provider did not return a usable answer.', $intent, $context, $search, $targetLocale);
        } catch (Throwable $exception) {
            return $this->fallbackAnswer($question, $sources, $exception->getMessage(), $intent, $context, $search, $targetLocale);
        }
    }

    public function sourcesForQuestion(string $question, ?User $user = null, array $context = [], ?array $intent = null, ?array $search = null): Collection
    {
        $context = $this->normalizeContext($context);
        $locale = $this->targetLocale($question, $user, $context);
        $context['locale'] = $locale;
        $intent ??= $this->detectIntent($question, $context, $user);
        $search ??= $this->searchContext($question, $context);
        $terms = $search['terms'];

        $dynamicSources = $terms === []
            ? collect()
            : $this->dynamicSourcesFor($intent['source_types'], $terms, $user, $search, $locale);

        $hybridSources = config('ai_platform.public_chat.hybrid_retrieval_enabled')
            ? $this->knowledgeSources($question, $locale)
            : collect();

        return $this->rankSources($dynamicSources, $search, $intent)
            ->merge($hybridSources)
            ->merge($this->pageContextSources($context, $intent, $locale))
            ->merge($this->platformGuideSources($question, $terms, $intent, $context, $locale))
            ->unique('id')
            ->take(18)
            ->values();
    }

    private function knowledgeSources(string $question, string $locale): Collection
    {
        return collect($this->knowledge->search($question, $locale, 8))->map(function (array $result): array {
            $type = $result['source_type'] === 'listing' ? 'business' : $result['source_type'];

            return [
                'id' => $result['source_type'].':'.$result['source_id'],
                'type' => $type,
                'title' => $result['title'],
                'summary' => Str::limit($result['content'], 260),
                'location' => null,
                'url' => $result['url'],
                'meta' => [
                    'retrieval' => 'hybrid',
                    'score' => $result['score'],
                ],
                'relevance_score' => (int) round($result['score'] * 1000),
            ];
        });
    }

    private function dynamicSourcesFor(array $sourceTypes, array $terms, ?User $user, array $search, string $locale): Collection
    {
        $dynamicSources = collect();

        foreach ($sourceTypes as $sourceType) {
            $dynamicSources = $dynamicSources->merge(match ($sourceType) {
                'business' => $this->listingSources($terms, $user, $search, $locale),
                'event' => $this->eventSources($terms, $user, $search, $locale),
                'article' => $this->articleSources($terms, $user, $search, $locale),
                'voucher' => $this->voucherSources($terms, $user, $search, $locale),
                'classified' => $this->classifiedSources($terms, $user, $search, $locale),
                'fault' => $this->faultSources($terms, $user, $search),
                default => collect(),
            });
        }

        return $dynamicSources->unique('id')->values();
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

    private function guidedAnswer(string $question, ?User $user, array $context, array $intent, array $search, string $locale): ?array
    {
        if ($this->isBusinessOnboardingQuestion($question)) {
            return $this->businessOnboardingAnswer($question, $context, $intent, $locale);
        }

        if (! $this->gateway->configured() && ($recommendation = $this->guidedPlatformRecommendation($question, $user, $context, $intent, $search, $locale))) {
            return $recommendation;
        }

        return null;
    }

    private function guidedPlatformRecommendation(string $question, ?User $user, array $context, array $intent, array $search, string $locale): ?array
    {
        $recommendation = $this->platformRecommendationFor($question, $intent, $search, $context);

        if ($recommendation === null) {
            return null;
        }

        $recommendationSearch = array_replace($search, [
            'base_terms' => collect($search['base_terms'] ?? [])
                ->merge($recommendation['terms'])
                ->unique()
                ->values()
                ->all(),
            'terms' => collect($search['terms'] ?? [])
                ->merge($recommendation['terms'])
                ->unique()
                ->values()
                ->all(),
        ]);

        $sources = $this->rankSources(
            $this->dynamicSourcesFor($recommendation['source_types'], $recommendationSearch['terms'], $user, $recommendationSearch, $locale),
            $recommendationSearch,
            array_replace($intent, ['source_types' => $recommendation['source_types']])
        )
            ->sortBy(function (array $source) use ($recommendation): int {
                $typeIndex = array_search($source['type'] ?? '', $recommendation['source_types'], true);

                return (($typeIndex === false ? 99 : $typeIndex) * 1000) - (int) ($source['relevance_score'] ?? 0);
            })
            ->take(8)
            ->values();

        if ($sources->isEmpty()) {
            return null;
        }

        $first = (string) data_get($sources->first(), 'title', $this->t('recommendation.default_first', $locale));
        $answerKey = (string) ($recommendation['answer_key'] ?? 'recommendation.platform.answer');

        return [
            'ok' => true,
            'source' => 'guided',
            'answer' => $this->t($answerKey, $locale, [
                'count' => (string) $sources->count(),
                'first' => $first,
                'topic' => (string) $recommendation['topic'],
                'types' => $this->sourceSummary($sources, $locale),
            ]),
            'locale' => $locale,
            'confidence' => 0.88,
            'intent' => array_replace($intent, [
                'key' => $recommendation['intent'],
                'label' => $recommendation['label'],
                'confidence' => 0.9,
            ]),
            'search_context' => $this->publicSearchContext($recommendationSearch),
            'page_context' => $context,
            'sources' => $this->sourceCards($sources, array_replace($intent, ['key' => $recommendation['intent']]), $question, $context, $locale)->values()->all(),
            'answer_actions' => $this->answerActions(array_replace($intent, ['key' => $recommendation['intent']]), $sources, $question, $context, $recommendationSearch, $locale),
            'follow_up_questions' => collect($recommendation['follow_ups'] ?? [])
                ->map(fn (string $key): string => $this->t($key, $locale))
                ->values()
                ->all(),
            'search_url' => $this->recommendationSearchUrl($recommendation, $recommendationSearch),
        ];
    }

    private function recommendationSearchUrl(array $recommendation, array $search): string
    {
        $query = (string) ($recommendation['directory_query'] ?? implode(' ', $recommendation['terms'] ?? []));

        return match ($recommendation['intent'] ?? 'general') {
            'business_search', 'website_project', 'accommodation_search' => route('directory.index', array_filter([
                'q' => $query,
                'location' => $search['location'] ?? null,
            ])),
            'event_discovery' => route('events.index', array_filter(['q' => $query, 'location' => $search['location'] ?? null])),
            'voucher_discovery' => route('vouchers.index', array_filter(['q' => $query])),
            'classified_discovery' => route('classifieds.index', array_filter(['q' => $query, 'location' => $search['location'] ?? null])),
            'fault_reporting' => route('faults.index', array_filter(['category' => $search['base_terms'][0] ?? null])),
            'transport_help' => route('transport.index'),
            'article_lookup' => route('articles.index', array_filter(['q' => $query])),
            default => route('search.index', ['q' => $query]),
        };
    }

    private function platformRecommendationFor(string $question, array $intent, array $search, array $context): ?array
    {
        $normalized = ' '.mb_strtolower((string) preg_replace('/[^\pL\pN&]+/u', ' ', $question.' '.($context['page_heading'] ?? '').' '.($context['page_type'] ?? ''))).' ';
        $intentKey = (string) ($intent['key'] ?? 'general');
        $hasNeedSignal = $this->hasRecommendationSignal($normalized);

        foreach ($this->platformRecommendationProfiles() as $recommendation) {
            $matchedByMarker = $this->containsAny($normalized, $recommendation['markers'] ?? []);
            $matchedByIntent = $intentKey === ($recommendation['intent'] ?? null);

            if (($matchedByMarker || ($matchedByIntent && $hasNeedSignal)) && $this->recommendationHasSearchValue($recommendation, $search)) {
                return $recommendation;
            }
        }

        if (! $hasNeedSignal || empty($search['base_terms'] ?? [])) {
            return null;
        }

        return [
            'key' => 'platform',
            'intent' => $intentKey === 'general' ? 'platform_recommendation' : $intentKey,
            'label' => $intent['label'] ?? 'Platform recommendation',
            'topic' => implode(' ', $search['base_terms']),
            'directory_query' => implode(' ', $search['base_terms']),
            'terms' => $search['terms'] ?? $search['base_terms'],
            'source_types' => $intent['source_types'] ?? ['business', 'event', 'voucher', 'article', 'classified', 'fault'],
            'answer_key' => 'recommendation.platform.answer',
            'follow_ups' => ['follow.narrow_need', 'follow.which_town'],
        ];
    }

    private function recommendationHasSearchValue(array $recommendation, array $search): bool
    {
        if (! empty($recommendation['terms'] ?? [])) {
            return true;
        }

        return ! empty($search['base_terms'] ?? []);
    }

    private function hasRecommendationSignal(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            ' need ', ' looking for ', ' find ', ' show me ', ' recommend ', ' where can ', ' who can ',
            ' take me ', ' help me find ', ' get ', ' book ', ' buy ', ' sell ', ' report ',
            ' soek ', ' benodig ', ' waar kan ', ' wys my ', ' beveel ', ' koop ', ' verkoop ',
        ]);
    }

    private function platformRecommendationProfiles(): array
    {
        return [
            [
                'key' => 'website',
                'intent' => 'website_project',
                'label' => 'Website or developer recommendation',
                'topic' => 'website, web app, online store, or developer help',
                'directory_query' => 'developer website',
                'terms' => ['developer', 'developers', 'website', 'web', 'web design', 'web development', 'digital', 'software', 'online store', 'ecommerce'],
                'source_types' => ['business', 'classified', 'article', 'voucher', 'event', 'fault'],
                'markers' => [' website ', ' web site ', ' web developer ', ' web developers ', ' developer ', ' developers ', ' build a site ', ' build me a site ', ' online store ', ' ecommerce ', ' e commerce ', ' webwerf ', ' ontwikkelaar '],
                'answer_key' => 'recommendation.website.answer',
                'follow_ups' => ['follow.website_budget', 'follow.website_timeline'],
            ],
            [
                'key' => 'accommodation',
                'intent' => 'accommodation_search',
                'label' => 'Short-term accommodation recommendation',
                'topic' => 'short-term accommodation',
                'directory_query' => 'hotel b&b accommodation',
                'terms' => ['hotel', 'b&b', 'bnb', 'bed breakfast', 'guest house', 'guesthouse', 'accommodation', 'self catering', 'lodge', 'overnight', 'short term', 'stay'],
                'source_types' => ['business', 'classified', 'voucher', 'event', 'article', 'fault'],
                'markers' => [' place to stay ', ' stay short term ', ' short term stay ', ' short term accommodation ', ' accommodation ', ' hotel ', ' b&b ', ' bnb ', ' guest house ', ' guesthouse ', ' lodge ', ' overnight ', ' sleep ', ' verblyf ', ' gastehuis '],
                'answer_key' => 'recommendation.accommodation.answer',
                'follow_ups' => ['follow.accommodation_dates', 'follow.accommodation_town'],
            ],
            [
                'key' => 'directory',
                'intent' => 'business_search',
                'label' => 'Business or service recommendation',
                'topic' => 'local businesses and services',
                'directory_query' => 'business service',
                'terms' => [],
                'source_types' => ['business', 'voucher', 'event', 'article', 'classified', 'fault'],
                'markers' => [' business ', ' service ', ' services ', ' shop ', ' store ', ' mechanic ', ' plumber ', ' restaurant ', ' doctor ', ' dentist ', ' salon ', ' electrician ', ' builder ', ' contractor ', ' besigheid ', ' winkel ', ' diens '],
                'answer_key' => 'recommendation.platform.answer',
                'follow_ups' => ['follow.which_town', 'follow.narrow_need'],
            ],
            [
                'key' => 'events',
                'intent' => 'event_discovery',
                'label' => 'Event recommendation',
                'topic' => 'local events',
                'directory_query' => 'events',
                'terms' => [],
                'source_types' => ['event', 'business', 'voucher', 'article', 'classified', 'fault'],
                'markers' => [' event ', ' events ', ' weekend ', ' today ', ' tonight ', ' tomorrow ', ' festival ', ' market ', ' concert ', ' geleentheid ', ' gebeure '],
                'answer_key' => 'recommendation.platform.answer',
                'follow_ups' => ['follow.event_date', 'follow.which_town'],
            ],
            [
                'key' => 'vouchers',
                'intent' => 'voucher_discovery',
                'label' => 'Voucher or offer recommendation',
                'topic' => 'vouchers, specials, discounts, and offers',
                'directory_query' => 'voucher special discount',
                'terms' => [],
                'source_types' => ['voucher', 'business', 'event', 'article', 'classified', 'fault'],
                'markers' => [' voucher ', ' deal ', ' deals ', ' special ', ' specials ', ' discount ', ' discounts ', ' offer ', ' offers ', ' coupon ', ' promo ', ' koepon ', ' aanbod '],
                'answer_key' => 'recommendation.platform.answer',
                'follow_ups' => ['follow.offer_type', 'follow.which_town'],
            ],
            [
                'key' => 'classifieds',
                'intent' => 'classified_discovery',
                'label' => 'Classified recommendation',
                'topic' => 'classifieds and local marketplace items',
                'directory_query' => 'classifieds',
                'terms' => [],
                'source_types' => ['classified', 'business', 'voucher', 'event', 'article', 'fault'],
                'markers' => [' classified ', ' classifieds ', ' for sale ', ' buy ', ' sell ', ' marketplace ', ' bakkie ', ' car ', ' furniture ', ' koop ', ' verkoop '],
                'answer_key' => 'recommendation.platform.answer',
                'follow_ups' => ['follow.classified_budget', 'follow.which_town'],
            ],
            [
                'key' => 'faults',
                'intent' => 'fault_reporting',
                'label' => 'Civic fault help',
                'topic' => 'civic fault reports',
                'directory_query' => 'fault report',
                'terms' => [],
                'source_types' => ['fault', 'article', 'business', 'event', 'voucher', 'classified'],
                'markers' => [' fault ', ' pothole ', ' water leak ', ' burst pipe ', ' streetlight ', ' dumping ', ' electricity ', ' outage ', ' report ', ' slaggat ', ' fout ', ' krag '],
                'answer_key' => 'recommendation.platform.answer',
                'follow_ups' => ['follow.fault_location', 'follow.fault_photo'],
            ],
            [
                'key' => 'transport',
                'intent' => 'transport_help',
                'label' => 'Transport help',
                'topic' => 'taxi, ride, delivery, parcel, or moving help',
                'directory_query' => 'taxi transport delivery',
                'terms' => ['taxi', 'transport', 'ride', 'delivery', 'parcel', 'moving', 'bakkie'],
                'source_types' => ['business', 'classified', 'article', 'event', 'voucher', 'fault'],
                'markers' => [' taxi ', ' transport ', ' ride ', ' delivery ', ' parcel ', ' move ', ' moving ', ' bakkie delivery ', ' vervoer ', ' aflewering '],
                'answer_key' => 'recommendation.platform.answer',
                'follow_ups' => ['follow.transport_pickup', 'follow.transport_load'],
            ],
            [
                'key' => 'articles',
                'intent' => 'article_lookup',
                'label' => 'Article or local update recommendation',
                'topic' => 'articles, stories, news, and local updates',
                'directory_query' => 'articles news',
                'terms' => [],
                'source_types' => ['article', 'event', 'business', 'fault', 'voucher', 'classified'],
                'markers' => [' article ', ' articles ', ' news ', ' story ', ' stories ', ' update ', ' latest ', ' explain ', ' nuus '],
                'answer_key' => 'recommendation.platform.answer',
                'follow_ups' => ['follow.article_topic', 'follow.which_town'],
            ],
        ];
    }

    private function businessOnboardingAnswer(string $question, array $context, array $intent, string $locale): array
    {
        $answer = $this->t('business_onboarding.answer', $locale);
        $sources = collect([
            [
                'id' => 'guide:add-listing',
                'type' => 'start',
                'title' => $this->t('business_onboarding.add_title', $locale),
                'summary' => $this->t('business_onboarding.add_summary', $locale),
                'location' => null,
                'url' => route('add-listing.index'),
                'meta' => [
                    'action' => $this->t('action.start_listing', $locale),
                ],
            ],
            [
                'id' => 'guide:advertise',
                'type' => 'packages',
                'title' => $this->t('business_onboarding.compare_title', $locale),
                'summary' => $this->t('business_onboarding.compare_summary', $locale),
                'location' => null,
                'url' => route('advertise.index'),
                'meta' => [
                    'action' => $this->t('action.compare_packages', $locale),
                ],
            ],
        ]);

        return [
            'ok' => true,
            'source' => 'guided',
            'answer' => $answer,
            'locale' => $locale,
            'confidence' => 0.9,
            'intent' => $intent,
            'search_context' => $this->publicSearchContext($this->searchContext($question, $context)),
            'page_context' => $context,
            'sources' => $this->sourceCards($sources, $intent, $question, $context, $locale)->values()->all(),
            'answer_actions' => $this->answerActions($intent, $sources, $question, $context, $this->searchContext($question, $context), $locale),
            'follow_up_questions' => [
                $this->t('follow.staff_or_self', $locale),
                $this->t('follow.business_town', $locale),
                $this->t('follow.business_addons', $locale),
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

    private function listingSources(array $terms, ?User $user = null, array $search = [], string $locale = 'en'): Collection
    {
        $query = Listing::with(['contentTranslations', 'categories.contentTranslations'])->published();

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
                'title' => $listing->localizedValue('title', $locale),
                'summary' => $this->summary($listing->localizedValue('excerpt', $locale) ?: $listing->localizedValue('description', $locale)),
                'location' => $this->location([$listing->localizedValue('city', $locale), $listing->localizedValue('region', $locale)]),
                'url' => route('directory.show', $listing),
                'meta' => [
                    'categories' => $listing->categories->map(fn ($category) => $category->localizedValue('name', $locale))->values()->all(),
                    'phone' => $listing->phone,
                    'website' => $listing->website_url,
                    'status' => $listing->status,
                    'featured' => $listing->is_featured,
                    'address' => $this->location([$listing->localizedValue('address_line', $locale), $listing->localizedValue('city', $locale), $listing->localizedValue('region', $locale)]),
                ],
            ]);
    }

    private function eventSources(array $terms, ?User $user = null, array $search = [], string $locale = 'en'): Collection
    {
        $query = Event::with(['contentTranslations', 'categories.contentTranslations', 'listing.contentTranslations'])->published();

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
                'title' => $event->localizedValue('title', $locale),
                'summary' => $this->summary($event->localizedValue('excerpt', $locale) ?: $event->localizedValue('description', $locale)),
                'location' => $this->location([$event->localizedValue('venue_name', $locale), $event->localizedValue('city', $locale), $event->localizedValue('region', $locale)]),
                'url' => route('events.show', $event),
                'meta' => [
                    'date' => $event->start_at?->format('Y-m-d H:i'),
                    'ends_at' => $event->end_at?->format('Y-m-d H:i'),
                    'categories' => $event->categories->map(fn ($category) => $category->localizedValue('name', $locale))->values()->all(),
                    'business' => $event->listing?->localizedValue('title', $locale),
                    'status' => $event->status,
                    'website' => $event->website_url,
                ],
            ]);
    }

    private function articleSources(array $terms, ?User $user = null, array $search = [], string $locale = 'en'): Collection
    {
        $query = Article::with(['author', 'contentTranslations', 'categories.contentTranslations'])->published();

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
                'title' => $article->localizedTitle($locale),
                'summary' => $this->summary($article->localizedExcerpt($locale) ?: $article->localizedBody($locale)),
                'location' => null,
                'url' => route('articles.show', $article),
                'meta' => [
                    'published' => $article->published_at?->format('Y-m-d'),
                    'author' => $article->author?->name,
                    'categories' => $article->categories->map(fn ($category) => $category->localizedValue('name', $locale))->values()->all(),
                    'status' => $article->status,
                ],
            ]);
    }

    private function voucherSources(array $terms, ?User $user = null, array $search = [], string $locale = 'en'): Collection
    {
        $query = Voucher::with(['contentTranslations', 'listing.contentTranslations'])
            ->active()
            ->whereHas('listing', fn (Builder $listing) => $listing->published());

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
                'title' => $voucher->localizedValue('title', $locale),
                'summary' => $this->summary($voucher->localizedValue('description', $locale) ?: $voucher->localizedValue('terms', $locale)),
                'location' => $this->location([$voucher->listing?->localizedValue('city', $locale), $voucher->listing?->localizedValue('region', $locale)]),
                'url' => route('vouchers.show', [$voucher->listing, $voucher]),
                'meta' => [
                    'business' => $voucher->listing?->localizedValue('title', $locale),
                    'value' => $voucher->formattedValue(),
                    'ends_at' => $voucher->end_at?->format('Y-m-d'),
                    'status' => $voucher->status,
                    'remaining' => $voucher->remainingUses(),
                    'phone' => $voucher->listing?->phone,
                ],
            ]);
    }

    private function classifiedSources(array $terms, ?User $user = null, array $search = [], string $locale = 'en'): Collection
    {
        $query = Classified::with('contentTranslations')->where('status', Classified::STATUS_PUBLISHED);

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
                'title' => $classified->localizedValue('title', $locale),
                'summary' => $this->summary($classified->localizedValue('description', $locale)),
                'location' => $this->location([$classified->localizedValue('city', $locale), $classified->localizedValue('region', $locale)]),
                'url' => route('classifieds.show', $classified),
                'meta' => [
                    'price' => $classified->contact_for_price ? $this->t('meta.contact_for_price', $locale) : ($classified->price !== null ? $classified->currency.' '.number_format((float) $classified->price, 2) : null),
                    'status' => $classified->status,
                ],
            ]);
    }

    private function faultSources(array $terms, ?User $user = null, array $search = []): Collection
    {
        $query = CivicFaultReport::query()->where('is_approved', true);

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

    private function platformGuideSources(string $question, array $terms, array $intent, array $context, string $locale): Collection
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

        $guides = collect($this->platformGuides($locale));

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

    private function platformGuides(string $locale = 'en'): array
    {
        $guides = [
            [
                'id' => 'guide:search',
                'type' => 'guide',
                'title' => 'Ask Life can help you navigate Life@',
                'summary' => 'Ask Life can help users find local businesses, articles, events, vouchers, classifieds, civic faults, transport help, and business onboarding steps. It should be honest when Life@ does not have a verified record yet.',
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
                'summary' => 'Life@ articles cover local stories and community updates. Ask Life should summarize only published article records supplied to it.',
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
                'summary' => 'When a user needs human help, Life@ contact is the safest next step. Ask Life should not pretend to make official decisions or access private admin records.',
                'location' => 'Life@ Support',
                'url' => route('contact.index'),
                'meta' => [
                    'best_for' => 'Human support, uncertainty, corrections, or private account help.',
                ],
                'keywords' => ['contact', 'support', 'person', 'human', 'helpdesk', 'private', 'account', 'payment', 'billing', 'ondersteuning'],
            ],
        ];

        if ($locale !== 'af') {
            return $guides;
        }

        $afrikaans = [
            'guide:search' => [
                'title' => 'Ask Life kan jou help om Life@ te gebruik',
                'summary' => 'Ask Life kan mense help om plaaslike besighede, artikels, geleenthede, koopbewyse, geklassifiseerde advertensies, burgerlike foutverslae, vervoerhulp en besigheid-aanboordstappe te vind. Dit moet eerlik wees wanneer Life@ nog nie n geverifieerde rekord het nie.',
                'location' => 'Life@',
                'best_for' => 'Algemene hulp, opvolgvrae, en om die regte Life@ afdeling te vind.',
            ],
            'guide:directory' => [
                'title' => 'Besigheidsgids',
                'summary' => 'Die gids wys gepubliseerde plaaslike besighede. Mense kan volgens besigheidstipe, kategorie, dorp, diens of besigheidsnaam soek.',
                'location' => 'Life@ Gids',
                'best_for' => 'Om besighede en dienste te vind.',
            ],
            'guide:business-owner' => [
                'title' => 'Besigheidseienaar gereedskap',
                'summary' => 'Ingetekende besigheidseienaars kan listings verbeter, fotos byvoeg, geleenthede skep, advertensies loop, stootveldtogte stuur, en koopbewyse publiseer vanuit die listing-werkspasie.',
                'location' => 'Life@ Rekening',
                'best_for' => 'Om n besigheidsprofiel te verbeter of die volgende stap na n listing te kies.',
            ],
            'guide:add-listing' => [
                'title' => 'Voeg n besigheidslisting by',
                'summary' => 'Besigheidseienaars kan n listing begin, personeel-ondersteunde of selfdiens aanboord kies, en advertensie-opsies ontsluit nadat die listingreis begin.',
                'location' => 'Life@ Aanboord',
                'best_for' => 'Om n besigheid op Life@ by te voeg of te registreer.',
            ],
            'guide:advertise' => [
                'title' => 'Advertensies en pakkette',
                'summary' => 'Life@ advertensie-opsies sluit gidspakkette, geleentheidsigbaarheid, advertensies, stootveldtogte en koopbewyse in. Besigheidseienaars moet kies volgens of hulle ontdekking, bereik, herhalende besoeke of n spesifieke promosie nodig het.',
                'location' => 'Life@ Adverteer',
                'best_for' => 'Om pakkette, veldtogte en promosiegereedskap te kies.',
            ],
            'guide:events' => [
                'title' => 'Geleenthede',
                'summary' => 'Life@ geleenthede help mense ontdek wat naby gebeur. Besonderhede moet uit gepubliseerde geleentheidsrekords kom.',
                'location' => 'Life@ Geleenthede',
                'best_for' => 'Om plaaslike geleenthede en datums te vind.',
            ],
            'guide:articles' => [
                'title' => 'Artikels en plaaslike opdaterings',
                'summary' => 'Life@ artikels dek plaaslike stories en gemeenskapsopdaterings. Ask Life moet net gepubliseerde artikelrekords opsom wat verskaf is.',
                'location' => 'Life@ Artikels',
                'best_for' => 'Plaaslike nuus, opdaterings en verduidelikings.',
            ],
            'guide:vouchers' => [
                'title' => 'Koopbewyse en aanbiedinge',
                'summary' => 'Life@ koopbewyse wys aktiewe aanbiedinge van gelyste besighede. Waarde en bepalings moet uit gepubliseerde koopbewysrekords kom.',
                'location' => 'Life@ Koopbewyse',
                'best_for' => 'Aanbiedinge, spesiale pryse, afslag en plaaslike promosies.',
            ],
            'guide:classifieds' => [
                'title' => 'Geklassifiseerde advertensies',
                'summary' => 'Life@ geklassifiseerdes help mense om plaaslike items en gemeenskapsinskrywings te vind of in te dien. Pryse en beskikbaarheid moet uit gepubliseerde rekords kom.',
                'location' => 'Life@ Geklassifiseerdes',
                'best_for' => 'Koop, verkoop, en blaai deur plaaslike items.',
            ],
            'guide:faults' => [
                'title' => 'Burgerlike foutverslae',
                'summary' => 'Life@ foute help mense om goedgekeurde burgerlike probleme soos waterlekke, slaggate, straatligte, storting en elektrisiteitsprobleme te sien en aan te meld.',
                'location' => 'Life@ Foute',
                'best_for' => 'Om plaaslike burgerlike probleme te sien of aan te meld.',
            ],
            'guide:transport' => [
                'title' => 'Vervoerhulp',
                'summary' => 'Life@ vervoer kan mense lei na rit-, taxi-, aflewerings- of trekversoek werkstrome wanneer daardie dienste beskikbaar is.',
                'location' => 'Life@ Vervoer',
                'best_for' => 'Taxi, aflewering, bakkie, pakkie en trekhulp.',
            ],
            'guide:contact' => [
                'title' => 'Kontak Life@',
                'summary' => 'Wanneer iemand menslike hulp nodig het, is Life@ kontak die veiligste volgende stap. Ask Life moet nie voorgee dat dit amptelike besluite neem of private adminrekords kan sien nie.',
                'location' => 'Life@ Ondersteuning',
                'best_for' => 'Menslike ondersteuning, onsekerheid, regstellings of private rekeninghulp.',
            ],
        ];

        return collect($guides)
            ->map(function (array $guide) use ($afrikaans): array {
                $translation = $afrikaans[$guide['id']] ?? [];

                return array_replace($guide, [
                    'title' => $translation['title'] ?? $guide['title'],
                    'summary' => $translation['summary'] ?? $guide['summary'],
                    'location' => $translation['location'] ?? $guide['location'],
                    'meta' => array_replace($guide['meta'] ?? [], [
                        'best_for' => $translation['best_for'] ?? data_get($guide, 'meta.best_for'),
                    ]),
                ]);
            })
            ->all();
    }

    private function fallbackAnswer(string $question, Collection $sources, string $reason, array $intent, array $context, array $search, string $locale): array
    {
        if ($sources->isNotEmpty() && $sources->every(fn (array $source): bool => ($source['type'] ?? null) === 'guide')) {
            return [
                'ok' => true,
                'source' => 'fallback',
                'answer' => $this->t('fallback.guides_answer', $locale),
                'locale' => $locale,
                'confidence' => 0.35,
                'intent' => $intent,
                'search_context' => $this->publicSearchContext($search),
                'page_context' => $context,
                'sources' => $this->sourceCards($sources->take(8), $intent, $question, $context, $locale)->values()->all(),
                'answer_actions' => $this->answerActions($intent, $sources, $question, $context, $search, $locale),
                'follow_up_questions' => [
                    $this->t('follow.which_town', $locale),
                    $this->t('follow.what_type', $locale),
                ],
                'search_url' => route('search.index', ['q' => $question]),
                'message' => $reason,
            ];
        }

        $topTypes = $this->sourceSummary($sources, $locale);

        $first = $sources->first();
        $answer = $topTypes !== ''
            ? $this->t('fallback.found_sources', $locale, ['types' => $topTypes, 'title' => (string) $first['title']])
            : $this->t('fallback.no_direct_match_short', $locale);

        return [
            'ok' => true,
            'source' => 'fallback',
            'answer' => $answer,
            'locale' => $locale,
            'confidence' => $sources->isEmpty() ? 0 : 0.45,
            'intent' => $intent,
            'search_context' => $this->publicSearchContext($search),
            'page_context' => $context,
            'sources' => $this->sourceCards($sources->take(8), $intent, $question, $context, $locale)->values()->all(),
            'answer_actions' => $this->answerActions($intent, $sources, $question, $context, $search, $locale),
            'follow_up_questions' => [
                $search['location']
                    ? $this->t('follow.show_more_near', $locale, ['location' => (string) $search['location']])
                    : $this->t('follow.add_town_or_category', $locale),
                $this->t('follow.search_phrase', $locale),
            ],
            'search_url' => route('search.index', ['q' => $question]),
            'message' => $reason,
        ];
    }

    private function targetLocale(string $question, ?User $user, array $context): string
    {
        $preferredLocales = [];

        foreach ([
            $user?->preferred_locale,
            $context['locale'] ?? null,
            app()->getLocale(),
        ] as $locale) {
            $normalized = $this->normalizeLocale($locale);

            if ($normalized === 'af') {
                return $normalized;
            }

            if ($normalized !== null) {
                $preferredLocales[] = $normalized;
            }
        }

        if ($this->detectLocale($question) === 'af') {
            return 'af';
        }

        return $preferredLocales[0] ?? 'en';
    }

    private function normalizeLocale(?string $locale): ?string
    {
        $locale = trim((string) $locale);

        if ($locale === '') {
            return null;
        }

        $locale = str_replace('_', '-', mb_strtolower($locale));
        $locale = explode('-', $locale)[0] ?: $locale;

        return array_key_exists($locale, (array) config('localization.supported', []))
            ? $locale
            : null;
    }

    private function localeName(string $locale): string
    {
        return (string) data_get(config('localization.supported'), "{$locale}.name", $locale);
    }

    private function languageInstruction(string $locale): string
    {
        return $locale === 'af'
            ? 'Answer in natural Afrikaans. Use the product name Ask Life. Keep Life@, business names, routes, URLs, and official place names unchanged where appropriate.'
            : 'Answer in natural South African English. Keep Life@, business names, routes, URLs, and official place names unchanged where appropriate.';
    }

    private function sourceTypeName(string $type, int $count, string $locale): string
    {
        $singular = $this->t('type.'.$type, $locale);

        if ($count === 1) {
            return $singular;
        }

        return $this->t('type_plural.'.$type, $locale, [], $singular.'s');
    }

    private function sourceSummary(Collection $sources, string $locale): string
    {
        return $sources
            ->groupBy('type')
            ->map(fn (Collection $items, string $type): string => $items->count().' '.$this->sourceTypeName($type, $items->count(), $locale))
            ->values()
            ->implode(', ');
    }

    private function t(string $key, string $locale, array $replace = [], ?string $fallback = null): string
    {
        $locale = $this->normalizeLocale($locale) ?? 'en';
        $translations = $this->translations();
        $value = data_get($translations, "{$locale}.{$key}")
            ?? data_get($translations, "en.{$key}")
            ?? $fallback
            ?? $key;

        foreach ($replace as $name => $replacement) {
            $value = str_replace(':'.$name, (string) $replacement, (string) $value);
        }

        return (string) $value;
    }

    private function translations(): array
    {
        return [
            'en' => [
                'action' => [
                    'view' => 'View',
                    'call' => 'Call',
                    'website' => 'Website',
                    'directions' => 'Directions',
                    'report_fault' => 'Report fault',
                    'open_listings' => 'Open listings',
                    'open_best_match' => 'Open best match',
                    'full_search' => 'Full search',
                    'my_listings' => 'My listings',
                    'advertise' => 'Advertise',
                    'events' => 'Events',
                    'vouchers' => 'Vouchers',
                    'classifieds' => 'Classifieds',
                    'post_classified' => 'Post classified',
                    'fault_map' => 'Fault map',
                    'transport' => 'Transport',
                    'contact_life' => 'Contact Life@',
                    'listing_workspace' => 'Listing workspace',
                    'start_listing' => 'Start listing',
                    'compare_packages' => 'Compare packages',
                    'developers' => 'Developers',
                    'accommodation' => 'Accommodation',
                    'directory' => 'Directory',
                    'articles' => 'Articles',
                ],
                'label' => [
                    'business' => 'Business',
                    'event' => 'Event',
                    'article' => 'Article',
                    'voucher' => 'Voucher',
                    'classified' => 'Classified',
                    'fault' => 'Fault',
                    'guide' => 'Guide',
                    'page' => 'Current page',
                    'start' => 'Start',
                    'packages' => 'Packages',
                ],
                'type' => [
                    'business' => 'business',
                    'event' => 'event',
                    'article' => 'article',
                    'voucher' => 'voucher',
                    'classified' => 'classified',
                    'fault' => 'fault',
                    'guide' => 'guide',
                    'page' => 'page',
                    'start' => 'start',
                    'packages' => 'package option',
                ],
                'type_plural' => [
                    'business' => 'businesses',
                    'event' => 'events',
                    'article' => 'articles',
                    'voucher' => 'vouchers',
                    'classified' => 'classifieds',
                    'fault' => 'faults',
                    'guide' => 'guides',
                    'page' => 'pages',
                    'start' => 'starts',
                    'packages' => 'package options',
                ],
                'meta' => [
                    'contact_for_price' => 'Contact for price',
                ],
                'business_onboarding' => [
                    'answer' => 'Yes. Start on Add Listing: create or log in to your account, enter your business name and town, choose a staff-assisted or self-service directory package, then continue to checkout. Once the listing is active, you can add events, adverts, push campaigns, and vouchers from your account.',
                    'add_title' => 'Add your business listing',
                    'add_summary' => 'Create a starter listing, choose a directory package, and continue to checkout.',
                    'compare_title' => 'Compare advertising and directory packages',
                    'compare_summary' => 'See directory, event, advert, push, and voucher options.',
                ],
                'recommendation' => [
                    'default_first' => 'the first match',
                    'website' => [
                        'answer' => 'Yes. For a website or online project, I would start with listed developers or digital providers on Life@. I found :count possible match(es); begin with :first, then compare the other source cards for fit, location, phone, and website details.',
                    ],
                    'accommodation' => [
                        'answer' => 'For a short-term place to stay, I would take you to listed accommodation providers first: hotels, B&Bs, guest houses, lodges, or self-catering options. I found :count possible match(es); start with :first and check the source cards for location and contact options.',
                    ],
                    'platform' => [
                        'answer' => 'Life@ has :types that match your need for :topic. I would start with :first, then use the source cards for contact details, directions, dates, offers, or the right next action. If that is not quite right, tell me the town, category, date, budget, or urgency and I will narrow it down.',
                    ],
                ],
                'fallback' => [
                    'guides_answer' => 'I can help you work out where to go on Life@: businesses, events, local articles, vouchers, classifieds, civic fault reports, transport help, and business onboarding. I will be honest when I do not have a verified Life@ record yet, and I will point you to the safest next step.',
                    'found_sources' => 'I found :types on Life@ that may help. Start with :title.',
                    'no_direct_match_short' => 'I could not find a direct Life@ match yet.',
                ],
                'follow' => [
                    'widen_location' => 'Should I widen this beyond :location?',
                    'which_town' => 'Which town should I search in?',
                    'what_type' => 'Are you looking for a business, event, article, voucher, classified, fault report, or transport help?',
                    'staff_or_self' => 'Do you want staff-assisted setup or self-service?',
                    'business_town' => 'Which town is your business in?',
                    'business_addons' => 'Do you also want adverts, events, push campaigns, or vouchers?',
                    'show_more_near' => 'Show me more near :location',
                    'add_town_or_category' => 'Try adding a town or category',
                    'search_phrase' => 'Search this phrase across Life@',
                    'website_budget' => 'Do you need a simple website, online store, or custom web app?',
                    'website_timeline' => 'What budget and launch timeline should I keep in mind?',
                    'accommodation_dates' => 'What dates do you need the stay for?',
                    'accommodation_town' => 'Which town or area should I focus on?',
                    'narrow_need' => 'What detail should I narrow this by: town, category, price, date, or urgency?',
                    'event_date' => 'Which date or weekend should I check?',
                    'offer_type' => 'What kind of special or discount are you hoping for?',
                    'classified_budget' => 'What budget or item condition should I keep in mind?',
                    'fault_location' => 'Where exactly is the fault?',
                    'fault_photo' => 'Do you have a photo or landmark for the report?',
                    'transport_pickup' => 'Where is the pickup and drop-off?',
                    'transport_load' => 'Is this a person, parcel, food delivery, or moving load?',
                    'article_topic' => 'Which local topic or town should I focus on?',
                ],
                'empty' => [
                    'near_me' => 'I can help with that, but I need the town first because Life@ does not have your precise location in this chat. Tell me the town or area and I will narrow it down.',
                    'event_discovery' => 'I could not find a matching Life@ event for that time or place yet. Try a town, venue, or event type and I will check again.',
                    'voucher_discovery' => 'I could not find a matching active Life@ voucher yet. Try the business name, town, or kind of special you want.',
                    'fault_reporting' => 'I could not find a matching approved fault report yet. You can open the fault map or report a new fault with a location and photo.',
                    'business_owner' => 'I could not find a matching listing workspace item yet. Tell me which business or task you mean, and I can point you to the next account action.',
                    'general' => 'I could not find a direct Life@ match yet. Try a more specific town, business type, event name, article topic, voucher, classified item, or fault category.',
                ],
                'page' => [
                    'current_title' => 'Current Life@ page',
                    'current_summary' => 'The user is currently viewing this Life@ page. Use it to answer follow-up questions about what they are looking at, while staying honest about facts not present in the page context.',
                    'listing_workspace_summary' => 'The user is in a listing workspace. Help them improve the listing, add photos, create events, run adverts, prepare push campaigns, publish vouchers, or find human support.',
                    'current_location' => 'Current page',
                ],
                'intent' => [
                    'current_page_help' => 'Current page help',
                ],
            ],
            'af' => [
                'action' => [
                    'view' => 'Maak oop',
                    'call' => 'Bel',
                    'website' => 'Webwerf',
                    'directions' => 'Aanwysings',
                    'report_fault' => 'Rapporteer fout',
                    'open_listings' => 'Maak listings oop',
                    'open_best_match' => 'Maak beste passing oop',
                    'full_search' => 'Volledige soektog',
                    'my_listings' => 'My listings',
                    'advertise' => 'Adverteer',
                    'events' => 'Geleenthede',
                    'vouchers' => 'Koopbewyse',
                    'classifieds' => 'Geklassifiseerdes',
                    'post_classified' => 'Plaas geklassifiseerde advertensie',
                    'fault_map' => 'Foutkaart',
                    'transport' => 'Vervoer',
                    'contact_life' => 'Kontak Life@',
                    'listing_workspace' => 'Listing-werkspasie',
                    'start_listing' => 'Begin listing',
                    'compare_packages' => 'Vergelyk pakkette',
                    'developers' => 'Ontwikkelaars',
                    'accommodation' => 'Verblyf',
                    'directory' => 'Gids',
                    'articles' => 'Artikels',
                ],
                'label' => [
                    'business' => 'Besigheid',
                    'event' => 'Geleentheid',
                    'article' => 'Artikel',
                    'voucher' => 'Koopbewys',
                    'classified' => 'Geklassifiseerde',
                    'fault' => 'Fout',
                    'guide' => 'Gids',
                    'page' => 'Huidige bladsy',
                    'start' => 'Begin',
                    'packages' => 'Pakkette',
                ],
                'type' => [
                    'business' => 'besigheid',
                    'event' => 'geleentheid',
                    'article' => 'artikel',
                    'voucher' => 'koopbewys',
                    'classified' => 'geklassifiseerde',
                    'fault' => 'fout',
                    'guide' => 'gids',
                    'page' => 'bladsy',
                    'start' => 'beginpunt',
                    'packages' => 'pakketopsie',
                ],
                'type_plural' => [
                    'business' => 'besighede',
                    'event' => 'geleenthede',
                    'article' => 'artikels',
                    'voucher' => 'koopbewyse',
                    'classified' => 'geklassifiseerdes',
                    'fault' => 'foute',
                    'guide' => 'gidse',
                    'page' => 'bladsye',
                    'start' => 'beginpunte',
                    'packages' => 'pakketopsies',
                ],
                'meta' => [
                    'contact_for_price' => 'Kontak vir prys',
                ],
                'business_onboarding' => [
                    'answer' => 'Ja. Begin by Voeg Listing By: skep of meld aan by jou rekening, vul jou besigheidsnaam en dorp in, kies n personeel-ondersteunde of selfdiens gidspakket, en gaan dan voort na betaalpunt. Sodra die listing aktief is, kan jy geleenthede, advertensies, stootveldtogte en koopbewyse vanuit jou rekening byvoeg.',
                    'add_title' => 'Voeg jou besigheidslisting by',
                    'add_summary' => 'Skep n beginlisting, kies n gidspakket, en gaan voort na betaalpunt.',
                    'compare_title' => 'Vergelyk advertensie- en gidspakkette',
                    'compare_summary' => 'Sien gids-, geleentheid-, advertensie-, stootveldtog- en koopbewysopsies.',
                ],
                'recommendation' => [
                    'default_first' => 'die eerste passing',
                    'website' => [
                        'answer' => 'Ja. Vir n webwerf of aanlyn projek sal ek eers gelyste ontwikkelaars of digitale verskaffers op Life@ aanbeveel. Ek het :count moontlike passing(s) gevind; begin by :first en vergelyk dan die ander bronkaarte vir pasmaat, ligging, foon en webwerfbesonderhede.',
                    ],
                    'accommodation' => [
                        'answer' => 'Vir korttermynverblyf sal ek jou eers na gelyste verblyfverskaffers neem: hotelle, B&Bs, gastehuise, lodges of selfsorgopsies. Ek het :count moontlike passing(s) gevind; begin by :first en kyk na die bronkaarte vir ligging en kontakopsies.',
                    ],
                    'platform' => [
                        'answer' => 'Life@ het :types wat pas by jou behoefte vir :topic. Ek sal by :first begin, en dan die bronkaarte gebruik vir kontakbesonderhede, aanwysings, datums, aanbiedinge of die regte volgende aksie. As dit nie heeltemal reg is nie, gee vir my die dorp, kategorie, datum, begroting of dringendheid en ek sal dit vernou.',
                    ],
                ],
                'fallback' => [
                    'guides_answer' => 'Ek is Ask Life. Ek kan jou help uitwerk waarheen om op Life@ te gaan: besighede, geleenthede, plaaslike artikels, koopbewyse, geklassifiseerdes, burgerlike foutverslae, vervoerhulp en besigheid-aanboord. Ek sal eerlik wees wanneer Life@ nog nie n geverifieerde rekord het nie, en ek sal jou na die veiligste volgende stap wys.',
                    'found_sources' => 'Ek het :types op Life@ gevind wat dalk kan help. Begin by :title.',
                    'no_direct_match_short' => 'Ek kon nog nie n direkte Life@ passing vind nie.',
                ],
                'follow' => [
                    'widen_location' => 'Moet ek wyer as :location soek?',
                    'which_town' => 'In watter dorp moet ek soek?',
                    'what_type' => 'Soek jy n besigheid, geleentheid, artikel, koopbewys, geklassifiseerde advertensie, foutverslag of vervoerhulp?',
                    'staff_or_self' => 'Wil jy personeel-ondersteunde opstelling of selfdiens he?',
                    'business_town' => 'In watter dorp is jou besigheid?',
                    'business_addons' => 'Wil jy ook advertensies, geleenthede, stootveldtogte of koopbewyse he?',
                    'show_more_near' => 'Wys my meer naby :location',
                    'add_town_or_category' => 'Probeer n dorp of kategorie byvoeg',
                    'search_phrase' => 'Soek hierdie frase deur Life@',
                    'website_budget' => 'Het jy n eenvoudige webwerf, aanlyn winkel of pasgemaakte webtoep nodig?',
                    'website_timeline' => 'Watter begroting en bekendstellingsdatum moet ek in gedagte hou?',
                    'accommodation_dates' => 'Vir watter datums het jy verblyf nodig?',
                    'accommodation_town' => 'Op watter dorp of area moet ek fokus?',
                    'narrow_need' => 'Waarmee moet ek dit vernou: dorp, kategorie, prys, datum of dringendheid?',
                    'event_date' => 'Watter datum of naweek moet ek nagaan?',
                    'offer_type' => 'Watter soort spesiale aanbod of afslag soek jy?',
                    'classified_budget' => 'Watter begroting of toestand moet ek in gedagte hou?',
                    'fault_location' => 'Waar presies is die fout?',
                    'fault_photo' => 'Het jy n foto of landmerk vir die verslag?',
                    'transport_pickup' => 'Waar is die optel- en aflaaipunt?',
                    'transport_load' => 'Is dit n persoon, pakkie, kosaflewering of treklading?',
                    'article_topic' => 'Op watter plaaslike onderwerp of dorp moet ek fokus?',
                ],
                'empty' => [
                    'near_me' => 'Ek kan daarmee help, maar ek het eers die dorp nodig omdat Life@ nie jou presiese ligging in hierdie klets het nie. Gee vir my die dorp of area en ek sal dit vernou.',
                    'event_discovery' => 'Ek kon nog nie n passende Life@ geleentheid vir daardie tyd of plek vind nie. Probeer n dorp, venue of soort geleentheid en ek sal weer kyk.',
                    'voucher_discovery' => 'Ek kon nog nie n passende aktiewe Life@ koopbewys vind nie. Probeer die besigheidsnaam, dorp of soort aanbieding wat jy soek.',
                    'fault_reporting' => 'Ek kon nog nie n passende goedgekeurde foutverslag vind nie. Jy kan die foutkaart oopmaak of n nuwe fout met n ligging en foto rapporteer.',
                    'business_owner' => 'Ek kon nog nie n passende listing-werkspasie item vind nie. Se vir my watter besigheid of taak jy bedoel, en ek kan jou na die volgende rekeningaksie wys.',
                    'general' => 'Ek kon nog nie n direkte Life@ passing vind nie. Probeer n meer spesifieke dorp, besigheidstipe, geleentheidsnaam, artikelonderwerp, koopbewys, geklassifiseerde item of foutkategorie.',
                ],
                'page' => [
                    'current_title' => 'Huidige Life@ bladsy',
                    'current_summary' => 'Die gebruiker kyk tans na hierdie Life@ bladsy. Gebruik dit om opvolgvrae te beantwoord oor waarna hulle kyk, terwyl jy eerlik bly oor feite wat nie in die bladsykonteks is nie.',
                    'listing_workspace_summary' => 'Die gebruiker is in n listing-werkspasie. Help hulle om die listing te verbeter, fotos by te voeg, geleenthede te skep, advertensies te loop, stootveldtogte voor te berei, koopbewyse te publiseer, of menslike ondersteuning te vind.',
                    'current_location' => 'Huidige bladsy',
                ],
                'intent' => [
                    'current_page_help' => 'Huidige bladsy hulp',
                ],
            ],
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
            'website_project' => [
                'label' => 'Website or developer recommendation',
                'source_types' => ['business', 'classified', 'article', 'voucher', 'event', 'fault'],
                'markers' => [' website ', ' web site ', ' web developer ', ' web developers ', ' developer ', ' developers ', ' build a site ', ' build me a site ', ' online store ', ' ecommerce ', ' webwerf ', ' ontwikkelaar '],
            ],
            'accommodation_search' => [
                'label' => 'Short-term accommodation',
                'source_types' => ['business', 'classified', 'voucher', 'event', 'article', 'fault'],
                'markers' => [' accommodation ', ' hotel ', ' b&b ', ' bnb ', ' guest house ', ' guesthouse ', ' lodge ', ' overnight ', ' place to stay ', ' short term stay ', ' verblyf ', ' gastehuis '],
            ],
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
                'markers' => [' event ', ' events ', ' weekend ', ' today ', ' tonight ', ' tomorrow ', ' festival ', ' market ', ' concert ', ' geleentheid ', ' gebeure '],
            ],
            'voucher_discovery' => [
                'label' => 'Find offers',
                'source_types' => ['voucher', 'business', 'event', 'article', 'classified', 'fault'],
                'markers' => [' voucher ', ' deal ', ' deals ', ' special ', ' specials ', ' discount ', ' discounts ', ' offer ', ' offers ', ' coupon ', ' promo ', ' koepon ', ' aanbod '],
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

        $intentPriority = [
            'website_project',
            'accommodation_search',
            'business_owner',
            'voucher_discovery',
            'event_discovery',
            'classified_discovery',
            'fault_reporting',
            'transport_help',
            'article_lookup',
            'business_search',
            'support',
        ];

        foreach ($intentPriority as $key) {
            $definition = $definitions[$key];

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
            'website' => ['web', 'developer', 'developers', 'design', 'digital', 'software', 'online', 'ecommerce'],
            'developer' => ['developers', 'website', 'web', 'software', 'digital', 'online'],
            'hotel' => ['accommodation', 'b&b', 'bnb', 'guesthouse', 'guest', 'lodge', 'overnight', 'stay'],
            'accommodation' => ['hotel', 'b&b', 'bnb', 'guesthouse', 'guest', 'lodge', 'self catering', 'overnight'],
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

    private function pageContextSources(array $context, array $intent, string $locale): Collection
    {
        if (($context['page_type'] ?? 'general') === 'general') {
            return collect();
        }

        $title = $context['page_heading'] ?? $context['page_title'] ?? $this->t('page.current_title', $locale);
        $summary = $this->t('page.current_summary', $locale);

        if (($context['page_type'] ?? '') === 'account_listing_workspace') {
            $summary = $this->t('page.listing_workspace_summary', $locale);
        }

        return collect([
            [
                'id' => 'page:current',
                'type' => 'page',
                'title' => $title,
                'summary' => $summary,
                'location' => $this->t('page.current_location', $locale),
                'url' => $context['page_url'] ?? null,
                'meta' => [
                    'page_type' => $context['page_type'] ?? 'general',
                    'intent' => $intent['label'] ?? $this->t('intent.current_page_help', $locale),
                ],
            ],
        ]);
    }

    private function guideIdsForIntent(string $intent): array
    {
        return match ($intent) {
            'business_owner' => ['guide:business-owner', 'guide:add-listing', 'guide:advertise', 'guide:contact'],
            'website_project' => ['guide:directory', 'guide:contact'],
            'accommodation_search' => ['guide:directory', 'guide:search'],
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

    private function sourceCards(Collection $sources, array $intent, string $question, array $context, string $locale): Collection
    {
        return $sources->map(function (array $source) use ($intent, $question, $context, $locale): array {
            unset($source['keywords']);

            $source['label'] = $this->sourceLabel((string) ($source['type'] ?? 'source'), $locale);
            $source['meta'] = collect($source['meta'] ?? [])
                ->reject(fn ($value): bool => $value === null || $value === '' || $value === [])
                ->all();
            $source['actions'] = $this->sourceActions($source, $intent, $question, $context, $locale);

            return $source;
        });
    }

    private function sourceActions(array $source, array $intent, string $question, array $context, string $locale): array
    {
        $actions = [];

        if (filled($source['url'] ?? null)) {
            $actions[] = $this->action($this->t('action.view', $locale), (string) $source['url'], 'primary');
        }

        if (filled(data_get($source, 'meta.phone'))) {
            $phone = preg_replace('/[^\d+]/', '', (string) data_get($source, 'meta.phone'));
            if ($phone !== '') {
                $actions[] = $this->action($this->t('action.call', $locale), 'tel:'.$phone, 'phone');
            }
        }

        if (filled(data_get($source, 'meta.website'))) {
            $actions[] = $this->action($this->t('action.website', $locale), (string) data_get($source, 'meta.website'), 'external', true);
        }

        $mapQuery = $this->mapQueryForSource($source);
        if ($mapQuery !== null) {
            $actions[] = $this->action($this->t('action.directions', $locale), $this->mapsUrl($mapQuery), 'directions', true);
        }

        if (($source['type'] ?? '') === 'fault') {
            $actions[] = $this->action($this->t('action.report_fault', $locale), route('faults.report.create'), 'secondary');
        }

        if (($source['id'] ?? '') === 'guide:business-owner') {
            $actions[] = $this->action($this->t('action.open_listings', $locale), route('account.listings.index'), 'secondary');
        }

        return collect($actions)
            ->unique(fn (array $action): string => $action['label'].'|'.$action['url'])
            ->take(4)
            ->values()
            ->all();
    }

    private function answerActions(array $intent, Collection $sources, string $question, array $context, array $search, string $locale): array
    {
        $actions = [];
        $first = $sources->first();

        if (is_array($first) && filled($first['url'] ?? null)) {
            $actions[] = $this->action($this->t('action.open_best_match', $locale), (string) $first['url'], 'primary');
        }

        $actions[] = $this->action($this->t('action.full_search', $locale), route('search.index', array_filter([
            'q' => $question,
            'loc' => $search['location'] ?? null,
        ])), 'search');

        foreach (match ($intent['key'] ?? 'general') {
            'business_owner' => [
                $this->action($this->t('action.my_listings', $locale), route('account.listings.index'), 'secondary'),
                $this->action($this->t('action.advertise', $locale), route('advertise.index'), 'secondary'),
            ],
            'business_search' => [
                $this->action($this->t('action.directory', $locale), route('directory.index', array_filter(['q' => implode(' ', $search['base_terms'] ?? []), 'location' => $search['location'] ?? null])), 'secondary'),
            ],
            'website_project' => [
                $this->action($this->t('action.developers', $locale), route('directory.index', array_filter(['q' => 'developer website', 'location' => $search['location'] ?? null])), 'secondary'),
                $this->action($this->t('action.contact_life', $locale), route('contact.index'), 'secondary'),
            ],
            'accommodation_search' => [
                $this->action($this->t('action.accommodation', $locale), route('directory.index', array_filter(['q' => 'hotel b&b accommodation', 'location' => $search['location'] ?? null])), 'secondary'),
            ],
            'event_discovery' => [
                $this->action($this->t('action.events', $locale), route('events.index', array_filter(['q' => $question, 'location' => $search['location'] ?? null])), 'secondary'),
            ],
            'voucher_discovery' => [
                $this->action($this->t('action.vouchers', $locale), route('vouchers.index'), 'secondary'),
            ],
            'classified_discovery' => [
                $this->action($this->t('action.classifieds', $locale), route('classifieds.index'), 'secondary'),
                $this->action($this->t('action.post_classified', $locale), route('classifieds.manage.create'), 'secondary'),
            ],
            'fault_reporting' => [
                $this->action($this->t('action.fault_map', $locale), route('faults.index'), 'secondary'),
                $this->action($this->t('action.report_fault', $locale), route('faults.report.create'), 'secondary'),
            ],
            'transport_help' => [
                $this->action($this->t('action.transport', $locale), route('transport.index'), 'secondary'),
            ],
            'article_lookup' => [
                $this->action($this->t('action.articles', $locale), route('articles.index', array_filter(['q' => implode(' ', $search['base_terms'] ?? [])])), 'secondary'),
            ],
            'support' => [
                $this->action($this->t('action.contact_life', $locale), route('contact.index'), 'secondary'),
            ],
            default => [],
        } as $action) {
            $actions[] = $action;
        }

        if (($context['page_type'] ?? '') === 'account_listing_workspace') {
            $actions[] = $this->action($this->t('action.listing_workspace', $locale), route('account.listings.index'), 'secondary');
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

    private function sourceLabel(string $type, string $locale): string
    {
        return match ($type) {
            'business' => $this->t('label.business', $locale),
            'event' => $this->t('label.event', $locale),
            'article' => $this->t('label.article', $locale),
            'voucher' => $this->t('label.voucher', $locale),
            'classified' => $this->t('label.classified', $locale),
            'fault' => $this->t('label.fault', $locale),
            'guide' => $this->t('label.guide', $locale),
            'page' => $this->t('label.page', $locale),
            'start' => $this->t('label.start', $locale),
            'packages' => $this->t('label.packages', $locale),
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

    private function emptyAnswer(array $intent, array $search, string $locale): string
    {
        if (($search['near_me'] ?? false) && ! $search['location']) {
            return $this->t('empty.near_me', $locale);
        }

        return match ($intent['key'] ?? 'general') {
            'event_discovery' => $this->t('empty.event_discovery', $locale),
            'voucher_discovery' => $this->t('empty.voucher_discovery', $locale),
            'fault_reporting' => $this->t('empty.fault_reporting', $locale),
            'business_owner' => $this->t('empty.business_owner', $locale),
            default => $this->t('empty.general', $locale),
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

        foreach (['dev', 'admin', 'editor', 'staff', 'writer', 'councillor', 'member'] as $role) {
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
