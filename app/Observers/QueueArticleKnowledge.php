<?php

namespace App\Observers;

use App\Jobs\SyncArticleKnowledge;
use App\Models\Article;

class QueueArticleKnowledge
{
    public function saved(Article $article): void
    {
        $this->dispatch($article);
    }

    public function deleted(Article $article): void
    {
        $this->dispatch($article);
    }

    private function dispatch(Article $article): void
    {
        if (config('ai_platform.knowledge.auto_index')) {
            SyncArticleKnowledge::dispatch((int) $article->getKey());
        }
    }
}
