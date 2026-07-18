<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\ArticleRevisionNote;
use App\Models\ResearchItem;
use App\Models\Tag;
use App\Models\User;
use App\Support\Ai\AiPromptCatalog;
use App\Support\Editorial\BriefFreshness;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JimmyWritingService
{
    public function __construct(
        private readonly AiGatewayService $gateway,
        private readonly AiPromptCatalog $prompts,
    ) {}

    public function draftApproved(int $limit = 3, ?User $user = null, array $briefIds = []): array
    {
        $limit = max(1, min(20, $limit));

        $briefs = ArticleBrief::query()
            ->with(['researchItem.researchSource', 'researchItem.snapshots', 'dossier.claims.evidence.snapshot', 'suggestedCategory', 'article'])
            ->where('status', ArticleBrief::STATUS_APPROVED)
            ->whereDoesntHave('article')
            ->when($briefIds !== [], fn ($query) => $query->whereIn('id', $briefIds))
            ->oldest('reviewed_at')
            ->oldest()
            ->limit($limit)
            ->get();

        $summary = [
            'processed' => 0,
            'created' => 0,
            'failed' => 0,
            'skipped' => 0,
            'articles' => [],
            'errors' => [],
        ];

        foreach ($briefs as $brief) {
            $summary['processed']++;
            $result = $this->draftFromBrief($brief, $user);

            if (($result['ok'] ?? false) && isset($result['article'])) {
                if (($result['skipped'] ?? false)) {
                    $summary['skipped']++;
                } else {
                    $summary['created']++;
                }

                $summary['articles'][] = $result['article'];
            } elseif (($result['skipped'] ?? false)) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
                $summary['errors'][] = [
                    'brief_id' => $brief->id,
                    'message' => $result['message'] ?? 'Jimmy draft generation failed.',
                ];
            }
        }

        return $summary;
    }

    public function draftFromBrief(ArticleBrief $brief, ?User $user = null): array
    {
        $brief->loadMissing(['researchItem.researchSource', 'researchItem.snapshots', 'dossier.claims.evidence.snapshot', 'suggestedCategory', 'article']);

        if ($brief->status !== ArticleBrief::STATUS_APPROVED) {
            return ['ok' => false, 'skipped' => true, 'message' => 'Only approved briefs can be drafted by Jimmy.'];
        }

        if ($brief->article) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'This brief already has an article draft.',
                'article' => $brief->article,
            ];
        }

        $freshness = $brief->freshness();

        if (! $freshness['approvable']) {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => BriefFreshness::approvalMessage($freshness),
            ];
        }

        if (config('ai_platform.editorial.evidence_writer_enabled') && ! $brief->dossier?->readyForWriting()) {
            return [
                'ok' => false,
                'skipped' => true,
                'message' => 'Jimmy requires an approved dossier with supporting evidence for every high-importance claim.',
            ];
        }

        $prompt = $this->prompts->get('jimmy_article_draft');
        $sourceContexts = $this->sourceContexts($brief);

        $result = $this->gateway->generateStructured(
            'jimmy_article_draft',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'unpublished Life@ article draft for human editor review',
                    'schema' => $prompt['schema'],
                    'house_style' => [
                        'Local Eastern Free State community-news tone.',
                        'Useful and calm, not sensational or national-tabloid style.',
                        'Short paragraphs, clear attribution, practical local context.',
                        'English source article plus Afrikaans translation in the same response.',
                    ],
                    'rules' => [
                        'Create a draft only; the article must still be reviewed by a human editor.',
                        'Use direct quotes only when they appear in the supplied source context.',
                        'If source context is thin or unavailable, write a shorter cautious draft and flag gaps.',
                        'Do not mention internal agent names in the public article body.',
                        'Do not present generated images as real news photos.',
                    ],
                ],
                'brief' => $this->briefContext($brief),
                'research_item' => $this->researchItemContext($brief->researchItem),
                'freshness_policy' => BriefFreshness::policyContext($brief->researchItem?->published_at),
                'source_contexts' => $sourceContexts,
            ],
            $brief,
            $user,
            $prompt['output_language'],
        );

        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $payload = (array) ($result['payload'] ?? []);
        $article = $this->createDraftArticle($brief, $payload, $user);

        if (($result['generation'] ?? null) instanceof AiGeneration) {
            $result['generation']->update([
                'status' => AiGeneration::STATUS_ACCEPTED,
                'reviewed_by' => $user?->id ?: $brief->reviewed_by,
                'reviewed_at' => now(),
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Jimmy draft article created.',
            'article' => $article->fresh(['categories', 'tags', 'contentTranslations', 'brief']),
            'generation' => ($result['generation'] ?? null)?->fresh(),
            'source_contexts' => $sourceContexts,
        ];
    }

    private function createDraftArticle(ArticleBrief $brief, array $payload, ?User $user): Article
    {
        $title = Str::limit($this->stringFrom($payload, 'title', $brief->title), 255, '');
        $body = $this->stringFrom($payload, 'body', $brief->angle ?: $brief->researchItem?->summary ?: '');
        $excerpt = Str::limit($this->stringFrom($payload, 'excerpt', $brief->angle ?: $brief->researchItem?->summary ?: ''), 1000, '');

        $article = Article::create([
            'article_brief_id' => $brief->id,
            'user_id' => $user?->id ?: $brief->reviewed_by,
            'title' => $title,
            'slug' => $this->uniqueArticleSlug($this->stringFrom($payload, 'slug', $title)),
            'excerpt' => $excerpt,
            'seo_title' => Str::limit($this->stringFrom($payload, 'seo_title', $title), 255, ''),
            'seo_description' => Str::limit($this->stringFrom($payload, 'seo_description', $excerpt), 500, ''),
            'body' => $body,
            'featured_image_prompt' => $this->stringFrom($payload, 'image_prompt', ''),
            'source_locale' => 'en',
            'status' => 'draft',
            'submitted_at' => null,
            'published_at' => null,
        ]);

        if ($brief->suggested_category_id) {
            $article->categories()->sync([$brief->suggested_category_id]);
        }

        $this->syncTags($article, $payload, $brief);
        $this->syncAfrikaansTranslation($article, $payload);
        $this->createEditorNote($article, $payload, $brief, $user);

        $brief->update(['status' => ArticleBrief::STATUS_DRAFTED]);

        return $article;
    }

    private function syncTags(Article $article, array $payload, ArticleBrief $brief): void
    {
        $tags = collect($payload['suggested_tags'] ?? $payload['tags'] ?? [])
            ->merge($brief->suggested_tags ?: [])
            ->filter(fn ($tag): bool => is_scalar($tag) && trim((string) $tag) !== '')
            ->map(fn ($tag): string => Str::limit(trim((string) $tag), 80, ''))
            ->unique(fn (string $tag): string => Str::lower($tag))
            ->values()
            ->take(10);

        $tagIds = $tags
            ->map(function (string $name): ?int {
                $slug = Str::slug($name);

                if ($slug === '') {
                    return null;
                }

                return Tag::query()->firstOrCreate(
                    ['type' => 'article', 'slug' => $slug],
                    ['name' => $name]
                )->id;
            })
            ->filter()
            ->values()
            ->all();

        if ($tagIds !== []) {
            $article->tags()->sync($tagIds);
        }
    }

    private function syncAfrikaansTranslation(Article $article, array $payload): void
    {
        $translation = data_get($payload, 'afrikaans_translation');

        if (! is_array($translation)) {
            return;
        }

        $content = collect(['title', 'excerpt', 'body'])
            ->mapWithKeys(function (string $field) use ($translation, $article): array {
                $value = $translation[$field] ?? null;

                return [$field => is_string($value) && trim($value) !== '' ? trim($value) : $article->getAttribute($field)];
            })
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->all();

        if ($content === []) {
            return;
        }

        $article->contentTranslations()->updateOrCreate(
            ['locale' => 'af'],
            [
                'content' => $content,
                'source_locale' => 'en',
                'source_hash' => $article->contentSourceHash(),
                'provider' => $this->gateway->provider(),
                'model' => $this->gateway->model(),
                'translated_at' => now(),
            ]
        );
    }

    private function createEditorNote(Article $article, array $payload, ArticleBrief $brief, ?User $user): void
    {
        $authorId = $user?->id ?: $brief->reviewed_by;

        if (! $authorId) {
            return;
        }

        $flags = $this->stringList($payload['editorial_flags'] ?? []);
        $sourceNotes = $this->stringFrom($payload, 'source_notes', '');
        $imagePrompt = $this->stringFrom($payload, 'image_prompt', '');
        $lines = collect([
            'Jimmy draft notes:',
            $sourceNotes !== '' ? 'Sources: '.$sourceNotes : null,
            $flags !== [] ? 'Editor checks: '.implode('; ', $flags) : null,
            $imagePrompt !== '' ? 'Image prompt for later image agent: '.$imagePrompt : null,
        ])->filter()->values();

        if ($lines->count() <= 1) {
            return;
        }

        ArticleRevisionNote::create([
            'article_id' => $article->id,
            'author_user_id' => $authorId,
            'status' => $article->status,
            'note' => $lines->implode("\n"),
        ]);
    }

    private function sourceContexts(ArticleBrief $brief): array
    {
        $evidence = collect($brief->dossier?->claims ?? [])
            ->flatMap(fn ($claim) => $claim->evidence->map(fn ($link): array => [
                'url' => $link->snapshot->url,
                'ok' => true,
                'status' => $link->snapshot->http_status,
                'content_type' => $link->snapshot->content_type,
                'content' => Str::limit($link->excerpt ?: $link->snapshot->content, 6000, ''),
                'claim' => $claim->claim,
                'stance' => $link->stance,
                'authority_score' => $link->authority_score,
                'snapshot_hash' => $link->snapshot->content_hash,
            ]));

        if ($evidence->isNotEmpty()) {
            return $evidence->unique(fn (array $item): string => $item['snapshot_hash'].'|'.$item['claim'])->take(12)->values()->all();
        }

        $snapshots = collect($brief->researchItem?->snapshots ?? [])->map(fn ($snapshot): array => [
            'url' => $snapshot->url,
            'ok' => $snapshot->fetch_error === null,
            'status' => $snapshot->http_status,
            'content_type' => $snapshot->content_type,
            'content' => Str::limit($snapshot->content, 6000, ''),
            'snapshot_hash' => $snapshot->content_hash,
        ]);

        if ($snapshots->isNotEmpty()) {
            return $snapshots->take(5)->values()->all();
        }

        $item = $brief->researchItem;

        return $item ? [[
            'url' => $item->source_url,
            'ok' => true,
            'status' => null,
            'content_type' => 'text/plain',
            'content' => trim($item->title."\n\n".$item->summary),
            'snapshot_hash' => hash('sha256', trim($item->title."\n\n".$item->summary)),
        ]] : [];
    }

    private function briefContext(ArticleBrief $brief): array
    {
        return [
            'id' => $brief->id,
            'title' => $brief->title,
            'angle' => $brief->angle,
            'source_urls' => $brief->source_urls ?: [],
            'suggested_category' => $brief->suggestedCategory ? [
                'id' => $brief->suggestedCategory->id,
                'name' => $brief->suggestedCategory->name,
                'slug' => $brief->suggestedCategory->slug,
            ] : null,
            'suggested_tags' => $brief->suggested_tags ?: [],
            'scores' => [
                'locality' => (float) $brief->locality_score,
                'newsworthiness' => $brief->freshnessAdjustedNewsworthinessScore(),
                'timeliness' => $brief->effectiveTimelinessScore(),
                'confidence' => (float) $brief->confidence_score,
                'duplicate_risk' => (float) $brief->duplicate_risk,
            ],
            'editorial_notes' => $brief->editorial_notes,
        ];
    }

    private function researchItemContext(?ResearchItem $item): array
    {
        if (! $item) {
            return [];
        }

        return [
            'id' => $item->id,
            'title' => $item->title,
            'summary' => $item->summary,
            'source_name' => $item->source_name,
            'source_type' => $item->source_type,
            'source_url' => $item->source_url,
            'published_at' => $item->published_at?->toDateTimeString(),
            'detected_locations' => $item->detected_locations ?: [],
            'detected_entities' => $item->detected_entities ?: [],
            'research_source' => [
                'name' => $item->researchSource?->name,
                'query' => $item->researchSource?->query,
                'metadata' => $item->researchSource?->metadata ?: [],
            ],
        ];
    }

    private function uniqueArticleSlug(string $candidate): string
    {
        $base = Str::slug($candidate);
        $base = Str::limit($base !== '' ? $base : 'life-at-draft', 180, '');
        $slug = $base;
        $suffix = 2;

        while (Article::query()->where('slug', $slug)->exists()) {
            $append = '-'.$suffix;
            $slug = Str::limit($base, 255 - strlen($append), '').$append;
            $suffix++;
        }

        return $slug;
    }

    private function stringFrom(array $payload, string $key, string $fallback): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function stringList(mixed $value): array
    {
        $items = $value instanceof Collection ? $value->all() : (is_array($value) ? $value : [$value]);

        return collect($items)
            ->filter(fn ($item): bool => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn ($item): string => Str::limit(trim((string) $item), 240, ''))
            ->values()
            ->all();
    }
}
