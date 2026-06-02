<?php

namespace App\Observers;

use App\Jobs\TranslatePublishedContent;
use App\Services\OpenRouterTranslationService;
use Illuminate\Database\Eloquent\Model;

class QueueContentTranslations
{
    public function saved(Model $model): void
    {
        if (! (bool) config('localization.auto_translate_on_publish', true)) {
            return;
        }

        if (! method_exists($model, 'translatableContent') || ! method_exists($model, 'translatableFields')) {
            return;
        }

        if (! $this->readyForTranslation($model) || ! $this->contentChanged($model)) {
            return;
        }

        if (! app(OpenRouterTranslationService::class)->configured()) {
            return;
        }

        TranslatePublishedContent::dispatch($model::class, $model->getKey())->afterCommit();
    }

    private function contentChanged(Model $model): bool
    {
        if ($model->wasRecentlyCreated) {
            return true;
        }

        return $model->wasChanged(array_merge(
            $model->translatableFields(),
            ['source_locale', 'status', 'published_at']
        ));
    }

    private function readyForTranslation(Model $model): bool
    {
        if ($this->hasAttribute($model, 'status')) {
            return (string) $model->getAttribute('status') === 'published';
        }

        if ($this->hasAttribute($model, 'published_at')) {
            return filled($model->getAttribute('published_at'));
        }

        return true;
    }

    private function hasAttribute(Model $model, string $attribute): bool
    {
        return array_key_exists($attribute, $model->getAttributes())
            || in_array($attribute, $model->getFillable(), true);
    }
}
