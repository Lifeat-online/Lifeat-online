<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\ClaimEvidence;
use App\Models\ContentSourceLink;
use App\Models\EditorialClaim;
use App\Models\EditorialDossier;
use App\Models\SourceSnapshot;
use App\Models\StoryCluster;
use App\Models\User;
use App\Services\ArticlePublicationService;
use App\Services\JimmyWritingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateEventArticleTool implements OperatorTool
{
    public function __construct(
        private readonly JimmyWritingService $jimmy,
        private readonly ArticlePublicationService $publication,
    ) {}

    public function name(): string
    {
        return 'editorial.create_event_article';
    }

    public function risk(): string
    {
        return 'R1';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'angle' => ['required', 'string', 'max:3000'],
            'primary_snapshot_id' => ['required', 'integer', 'exists:source_snapshots,id'],
            'source_snapshot_ids' => ['required', 'array', 'min:1', 'max:12'],
            'source_snapshot_ids.*' => ['integer', 'distinct', 'exists:source_snapshots,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'publish' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return hash('sha256', json_encode([
            'title' => Str::lower($arguments['title']),
            'sources' => $arguments['source_snapshot_ids'],
            'articles' => Article::query()->max('updated_at')?->getTimestamp(),
        ], JSON_UNESCAPED_SLASHES));
    }

    public function execute(User $user, array $arguments): array
    {
        if (! in_array((int) $arguments['primary_snapshot_id'], array_map('intval', $arguments['source_snapshot_ids']), true)) {
            throw new \InvalidArgumentException('The primary source must be included in source_snapshot_ids.');
        }
        $snapshots = SourceSnapshot::query()->with('researchItem.researchSource')
            ->whereIn('id', $arguments['source_snapshot_ids'])->get();
        $primary = $snapshots->firstWhere('id', (int) $arguments['primary_snapshot_id']);
        if (! $primary) {
            throw new \InvalidArgumentException('The selected primary source is unavailable.');
        }
        $evidence = $this->evidenceAssessment($snapshots, $primary);

        return DB::transaction(function () use ($user, $arguments, $snapshots, $primary, $evidence): array {
            $cluster = StoryCluster::query()->firstOrCreate(
                ['fingerprint' => hash('sha256', Str::lower(Str::squish($arguments['title'])))],
                ['title' => $arguments['title'], 'status' => 'open'],
            );
            $cluster->researchItems()->syncWithoutDetaching($snapshots->pluck('research_item_id')->unique()->all());
            $dossier = EditorialDossier::query()->firstOrCreate(
                ['story_cluster_id' => $cluster->id],
                [
                    'title' => $arguments['title'],
                    'summary' => $arguments['angle'],
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ],
            );
            if ($dossier->status !== 'approved') {
                $dossier->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            }
            $claim = EditorialClaim::query()->firstOrCreate(
                ['editorial_dossier_id' => $dossier->id, 'claim' => $arguments['title']],
                ['importance' => 'high', 'status' => 'supported'],
            );
            foreach ($snapshots as $snapshot) {
                ClaimEvidence::query()->firstOrCreate(
                    ['editorial_claim_id' => $claim->id, 'source_snapshot_id' => $snapshot->id, 'stance' => 'supports'],
                    [
                        'excerpt' => Str::limit($snapshot->content, 1200, ''),
                        'authority_score' => $snapshot->is($primary) ? 90 : 70,
                    ],
                );
            }

            $brief = ArticleBrief::create([
                'research_item_id' => $primary->research_item_id,
                'editorial_dossier_id' => $dossier->id,
                'suggested_category_id' => $arguments['category_id'] ?? null,
                'title' => $arguments['title'],
                'angle' => $arguments['angle'],
                'source_urls' => $snapshots->pluck('url')->unique()->values()->all(),
                'suggested_tags' => [],
                'locality_score' => 100,
                'newsworthiness_score' => 90,
                'timeliness_score' => 100,
                'confidence_score' => $evidence['sufficient'] ? 90 : 55,
                'duplicate_risk' => 0,
                'editorial_notes' => 'Created by the developer Operator Assistant from retained source snapshots.',
                'status' => ArticleBrief::STATUS_APPROVED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
            $draft = $this->jimmy->draftFromBrief($brief, $user);
            if (! ($draft['ok'] ?? false) || ! ($draft['article'] ?? null) instanceof Article) {
                throw new \RuntimeException((string) ($draft['message'] ?? 'The evidence-backed article draft failed.'));
            }
            $article = $draft['article'];
            foreach ($snapshots as $snapshot) {
                ContentSourceLink::query()->firstOrCreate([
                    'source_snapshot_id' => $snapshot->id,
                    'sourceable_type' => Article::class,
                    'sourceable_id' => $article->id,
                ], ['role' => $snapshot->is($primary) ? 'primary' : 'supporting']);
            }
            $publish = (bool) ($arguments['publish'] ?? true) && $evidence['sufficient'];
            if ($publish) {
                $article = $this->publication->transition($article, 'published', $user);
            }

            return [
                'article_id' => $article->id,
                'slug' => $article->slug,
                'status' => $article->status,
                'brief_id' => $brief->id,
                'dossier_id' => $dossier->id,
                'source_snapshot_ids' => $snapshots->modelKeys(),
                'evidence' => $evidence,
                'requires_input' => ! $publish,
                'question' => ! $publish ? 'Only one independent source supports this event. Add a corroborating source or confirm that the article should remain a draft.' : null,
                'message' => $publish ? 'Evidence-backed event article published.' : 'Article retained as a draft because corroborating evidence is insufficient.',
            ];
        });
    }

    private function evidenceAssessment($snapshots, SourceSnapshot $primary): array
    {
        $hosts = $snapshots->map(fn (SourceSnapshot $snapshot): string => preg_replace('/^www\./', '', strtolower((string) parse_url($snapshot->url, PHP_URL_HOST))))
            ->filter()->unique()->values();

        return [
            'sufficient' => $hosts->count() >= 2,
            'primary_snapshot_id' => $primary->id,
            'independent_host_count' => $hosts->count(),
        ];
    }
}
