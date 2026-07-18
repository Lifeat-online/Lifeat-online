<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\Setting;
use App\Models\User;

class ArticlePublicationService
{
    public function __construct(private readonly OpenRouterTranslationService $translator) {}

    public function transition(Article $article, string $status, User $editor): Article
    {
        $published = $status === 'published';
        $article->update([
            'status' => $status,
            'published_at' => $published ? ($article->published_at ?: now()) : null,
            'submitted_at' => in_array($status, ['pending_review', 'revision_requested', 'published'], true)
                ? ($article->submitted_at ?: now())
                : null,
            'editor_user_id' => $published ? $editor->id : $article->editor_user_id,
        ]);
        $article->refresh();

        if (! $published) {
            return $article;
        }

        $ratePerWord = (float) Setting::getValue('writer.per_word_rate', 0);
        $wordCount = $article->wordCount();
        ArticleWordLedger::updateOrCreate(['article_id' => $article->id], [
            'writer_user_id' => $article->user_id ?: $editor->id,
            'approved_by_user_id' => $editor->id,
            'word_count' => $wordCount,
            'rate_per_word' => $ratePerWord,
            'gross_amount' => round($wordCount * $ratePerWord, 2),
            'status' => 'pending',
            'approved_at' => now(),
        ]);

        foreach (array_keys((array) config('localization.supported')) as $locale) {
            if ($locale !== $article->sourceLocale()) {
                $this->translator->translateModel($article, $locale);
            }
        }

        return $article->refresh();
    }
}
