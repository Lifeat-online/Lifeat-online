<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\Category;
use App\Models\ResearchItem;
use App\Models\User;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EditorialBriefService
{
    public function __construct(
        private readonly AiGatewayService $gateway,
        private readonly AiPromptCatalog $prompts,
    ) {}

    public function generatePending(int $limit = 10, ?User $user = null, array $itemIds = []): array
    {
        $limit = max(1, min(50, $limit));

        $items = ResearchItem::query()
            ->with(['researchSource', 'brief'])
            ->whereDoesntHave('brief')
            ->where('status', ResearchItem::STATUS_NEW)
            ->when($itemIds !== [], fn ($query) => $query->whereIn('id', $itemIds))
            ->orderByDesc('published_at')
            ->orderByDesc('fetched_at')
            ->limit($limit)
            ->get();

        $summary = [
            'processed' => 0,
            'created' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($items as $item) {
            $summary['processed']++;
            $result = $this->generateForItem($item, $user);

            if (($result['ok'] ?? false) && isset($result['brief'])) {
                $summary['created']++;
            } elseif (($result['skipped'] ?? false)) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
                $summary['errors'][] = [
                    'item_id' => $item->id,
                    'message' => $result['message'] ?? 'Editorial brief generation failed.',
                ];
            }
        }

        return $summary;
    }

    public function generateForItem(ResearchItem $item, ?User $user = null): array
    {
        if ($item->brief()->exists()) {
            return ['ok' => false, 'skipped' => true, 'message' => 'Research item already has an article brief.'];
        }

        $prompt = $this->prompts->get('editorial_brief');
        $categories = $this->articleCategories();

        $result = $this->gateway->generateStructured(
            'editorial_brief',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'editorial content brief only',
                    'schema' => $prompt['schema'],
                    'allowed_categories' => $categories->map(fn (Category $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ])->values()->all(),
                    'rules' => [
                        'Do not write an article.',
                        'Prefer rejection or low scores if the story is not clearly local.',
                        'Do not invent facts or sources.',
                        'Flag press-release-only stories as lower newsworthiness unless there is a clear local public impact.',
                        'Use duplicate_risk when similar recent Life@ articles are supplied.',
                    ],
                ],
                'research_item' => $this->researchItemContext($item),
                'recent_life_articles' => $this->recentArticleContext(),
            ],
            $item,
            $user,
            $prompt['output_language'],
        );

        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $payload = (array) ($result['payload'] ?? []);
        $brief = ArticleBrief::create([
            'research_item_id' => $item->id,
            'ai_generation_id' => ($result['generation'] ?? null)?->id,
            'suggested_category_id' => $this->categoryIdFromPayload($payload, $categories),
            'title' => Str::limit($this->stringFrom($payload, 'title', $item->title), 255, ''),
            'angle' => $this->stringFrom($payload, 'angle', ''),
            'source_urls' => $this->sourceUrlsFrom($payload, $item),
            'suggested_tags' => $this->tagsFrom($payload),
            'locality_score' => $this->scoreFrom($payload, 'locality_score'),
            'newsworthiness_score' => $this->scoreFrom($payload, 'newsworthiness_score'),
            'confidence_score' => $this->scoreFrom($payload, 'confidence_score'),
            'duplicate_risk' => $this->scoreFrom($payload, 'duplicate_risk'),
            'editorial_notes' => $this->notesFrom($payload),
            'status' => ArticleBrief::STATUS_PENDING_REVIEW,
        ]);

        $item->update(['status' => ResearchItem::STATUS_BRIEFED]);

        return [
            'ok' => true,
            'message' => 'Editorial brief created.',
            'brief' => $brief->fresh(['researchItem', 'suggestedCategory']),
            'generation' => $result['generation'] ?? null,
        ];
    }

    private function articleCategories(): Collection
    {
        return Category::query()
            ->where('type', 'article')
            ->orderBy('name')
            ->get();
    }

    private function researchItemContext(ResearchItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'summary' => $item->summary,
            'source_name' => $item->source_name,
            'source_type' => $item->source_type,
            'source_url' => $item->source_url,
            'published_at' => $item->published_at?->toDateTimeString(),
            'detected_locations' => $item->detected_locations ?: [],
            'research_source' => [
                'name' => $item->researchSource?->name,
                'query' => $item->researchSource?->query,
                'metadata' => $item->researchSource?->metadata ?: [],
            ],
        ];
    }

    private function recentArticleContext(): array
    {
        return Article::query()
            ->latest()
            ->limit(30)
            ->get(['title', 'slug', 'excerpt', 'published_at'])
            ->map(fn (Article $article): array => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => Str::limit((string) $article->excerpt, 240, ''),
                'published_at' => $article->published_at?->toDateString(),
            ])
            ->all();
    }

    private function categoryIdFromPayload(array $payload, Collection $categories): ?int
    {
        $candidate = Str::lower(trim((string) ($payload['category'] ?? $payload['suggested_category'] ?? '')));

        if ($candidate === '') {
            return null;
        }

        $category = $categories->first(function (Category $category) use ($candidate): bool {
            return Str::lower($category->slug) === $candidate
                || Str::lower($category->name) === $candidate;
        });

        return $category?->id;
    }

    private function sourceUrlsFrom(array $payload, ResearchItem $item): array
    {
        $urls = $payload['source_urls'] ?? [];
        $urls = is_array($urls) ? $urls : [$urls];

        if ($item->source_url) {
            $urls[] = $item->source_url;
        }

        return collect($urls)
            ->filter(fn ($url): bool => is_string($url) && trim($url) !== '')
            ->map(fn (string $url): string => trim($url))
            ->unique()
            ->values()
            ->all();
    }

    private function tagsFrom(array $payload): array
    {
        $tags = $payload['suggested_tags'] ?? $payload['tags'] ?? [];
        $tags = is_array($tags) ? $tags : explode(',', (string) $tags);

        return collect($tags)
            ->filter(fn ($tag): bool => is_scalar($tag) && trim((string) $tag) !== '')
            ->map(fn ($tag): string => Str::limit(trim((string) $tag), 80, ''))
            ->unique()
            ->values()
            ->take(10)
            ->all();
    }

    private function scoreFrom(array $payload, string $key): float
    {
        $score = (float) ($payload[$key] ?? 0);

        if ($score > 0 && $score <= 1) {
            $score *= 100;
        }

        return max(0, min(100, round($score, 2)));
    }

    private function notesFrom(array $payload): string
    {
        $notes = trim((string) ($payload['editorial_notes'] ?? ''));
        $recommendation = trim((string) ($payload['recommendation'] ?? ''));

        if ($recommendation !== '') {
            $notes = trim($notes."\n\nRecommendation: ".$recommendation);
        }

        return $notes;
    }

    private function stringFrom(array $payload, string $key, string $fallback): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }
}
