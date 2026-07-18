<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\AiManagerAction;
use App\Models\Article;
use App\Models\User;

class ProposeArticleStatusTool implements OperatorTool
{
    public function name(): string { return 'content.propose_article_status'; }
    public function risk(): string { return 'R1'; }
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
        $article = Article::findOrFail($arguments['article_id']);
        $action = AiManagerAction::create([
            'action_key' => 'article-status-'.$article->id.'-'.$arguments['status'],
            'domain' => 'content',
            'action_type' => 'propose_article_status',
            'title' => 'Proposed article status change: '.$article->title,
            'summary' => "Change status from {$article->status} to {$arguments['status']}.",
            'rationale' => $arguments['reason'],
            'status' => AiManagerAction::STATUS_PROPOSED,
            'risk_level' => 'R1',
            'required_mode' => AiManagerAction::MODE_APPROVAL,
            'source_type' => Article::class,
            'source_id' => $article->id,
            'payload' => ['before' => ['status' => $article->status], 'proposed' => ['status' => $arguments['status']]],
            'proposed_by' => 'operator_assistant:user:'.$user->id,
        ]);

        return ['proposed' => true, 'action_id' => $action->id, 'article_id' => $article->id, 'current_status' => $article->status, 'proposed_status' => $arguments['status']];
    }
}
