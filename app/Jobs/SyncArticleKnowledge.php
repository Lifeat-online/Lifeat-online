<?php

namespace App\Jobs;

use App\Ai\Knowledge\KnowledgeIndexer;
use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncArticleKnowledge implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $articleId)
    {
        $this->afterCommit();
        $this->onQueue('ai-indexing');
    }

    public function handle(KnowledgeIndexer $indexer): void
    {
        $article = Article::query()->find($this->articleId);
        if ($article) {
            $indexer->indexArticle($article);

            return;
        }

        $indexer->removeArticleById($this->articleId);
    }
}
