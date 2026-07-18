<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Article;
use App\Models\User;

class ContentReviewQueueTool implements OperatorTool
{
    public function name(): string
    {
        return 'content.review_queue';
    }

    public function risk(): string
    {
        return 'R0';
    }

    public function rules(): array
    {
        return ['limit' => ['sometimes', 'integer', 'min:1', 'max:50']];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support', 'dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return 'read-only';
    }

    public function execute(User $user, array $arguments): array
    {
        $articles = Article::query()
            ->whereIn('status', ['draft', 'pending_review', 'revision_requested'])
            ->latest('updated_at')
            ->limit((int) ($arguments['limit'] ?? 10))
            ->get(['id', 'title', 'slug', 'status', 'updated_at']);

        return [
            'count' => $articles->count(),
            'articles' => $articles->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'status' => $article->status,
                'updated_at' => $article->updated_at?->toIso8601String(),
                'url' => route('admin.articles.edit', $article),
            ])->all(),
        ];
    }
}
