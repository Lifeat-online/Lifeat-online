<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Article;
use App\Models\User;
use App\Services\ArticlePublicationService;

class ApplyArticleStatusTool implements OperatorTool
{
    public function __construct(private readonly ArticlePublicationService $publication) {}

    public function name(): string { return 'content.apply_article_status'; }
    public function risk(): string { return 'R2'; }
    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', 'exists:articles,id'],
            'status' => ['required', 'string', 'in:draft,pending_review,revision_requested,published'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
    public function authorize(User $user): bool { return $user->hasRole('admin', 'editor', 'dev', 'developer'); }
    public function recordVersion(array $arguments): string
    {
        return hash('sha256', json_encode(Article::findOrFail($arguments['article_id'])->getAttributes(), JSON_UNESCAPED_SLASHES));
    }
    public function execute(User $user, array $arguments): array
    {
        $article = Article::query()->lockForUpdate()->findOrFail($arguments['article_id']);
        $before = ['status' => $article->status, 'published_at' => $article->published_at?->toIso8601String()];
        $article = $this->publication->transition($article, $arguments['status'], $user);

        return [
            'article_id' => $article->id,
            'before' => $before,
            'after' => ['status' => $article->status, 'published_at' => $article->published_at?->toIso8601String()],
            'verified' => $article->status === $arguments['status'],
        ];
    }
}
