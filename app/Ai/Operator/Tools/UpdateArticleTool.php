<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Article;
use App\Models\ContentSourceLink;
use App\Models\SourceSnapshot;
use App\Models\User;
use App\Services\ArticlePublicationService;

class UpdateArticleTool implements OperatorTool
{
    public function __construct(private readonly ArticlePublicationService $publication) {}

    public function name(): string
    {
        return 'editorial.update_article';
    }

    public function risk(): string
    {
        return 'R1';
    }

    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', 'exists:articles,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['sometimes', 'string', 'max:100000'],
            'status' => ['sometimes', 'string', 'in:draft,pending_review,revision_requested,published'],
            'category_ids' => ['sometimes', 'array', 'max:10'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'source_snapshot_ids' => ['sometimes', 'array', 'max:12'],
            'source_snapshot_ids.*' => ['integer', 'distinct', 'exists:source_snapshots,id'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return hash('sha256', json_encode(Article::findOrFail($arguments['article_id'])->getAttributes(), JSON_UNESCAPED_SLASHES));
    }

    public function execute(User $user, array $arguments): array
    {
        $article = Article::findOrFail($arguments['article_id']);
        $article->update(collect($arguments)->only(['title', 'excerpt', 'body'])->all());
        if (isset($arguments['category_ids'])) {
            $article->categories()->sync($arguments['category_ids']);
        }
        $snapshots = SourceSnapshot::query()->whereIn('id', $arguments['source_snapshot_ids'] ?? [])->get();
        foreach ($snapshots as $snapshot) {
            ContentSourceLink::query()->firstOrCreate(['source_snapshot_id' => $snapshot->id, 'sourceable_type' => Article::class, 'sourceable_id' => $article->id], ['role' => 'supporting']);
        }
        if (isset($arguments['status'])) {
            $article = $this->publication->transition($article, $arguments['status'], $user);
        }

        return ['article_id' => $article->id, 'slug' => $article->slug, 'status' => $article->fresh()->status, 'source_snapshot_ids' => $snapshots->modelKeys()];
    }
}
