<?php

namespace App\Models\Concerns;

use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasContentTranslations
{
    public function contentTranslations(): MorphMany
    {
        return $this->morphMany(ContentTranslation::class, 'translatable');
    }

    public function translationFor(?string $locale = null): ?ContentTranslation
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === $this->sourceLocale()) {
            return null;
        }

        if ($this->relationLoaded('contentTranslations')) {
            return $this->contentTranslations->firstWhere('locale', $locale);
        }

        return $this->contentTranslations()->where('locale', $locale)->first();
    }

    public function localizedContent(?string $locale = null): array
    {
        return $this->translationFor($locale)?->content ?: [];
    }

    public function localizedValue(string $field, ?string $locale = null): mixed
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === $this->sourceLocale()) {
            return $this->getAttribute($field);
        }

        $content = $this->localizedContent($locale);
        $value = $content[$field] ?? null;

        return $value !== null && $value !== '' ? $value : $this->getAttribute($field);
    }

    public function sourceLocale(): string
    {
        return (string) ($this->getAttribute('source_locale') ?: config('localization.default', 'en'));
    }

    public function contentSourceHash(): string
    {
        return hash('sha256', json_encode($this->translatableContent(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function translatableContent(): array
    {
        return collect($this->translatableFields())
            ->mapWithKeys(fn (string $field): array => [$field => $this->getAttribute($field)])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }

    public function translatableFields(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    public function translatedLocales(): Collection
    {
        $translations = $this->relationLoaded('contentTranslations')
            ? $this->contentTranslations
            : $this->contentTranslations()->get();

        return $translations->pluck('locale')->values();
    }
}
