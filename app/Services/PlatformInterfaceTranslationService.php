<?php

namespace App\Services;

use App\Models\InterfaceTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PlatformInterfaceTranslationService
{
    public function __construct(private readonly OpenRouterTranslationService $translator)
    {
    }

    public function status(): array
    {
        $source = $this->sourceStrings();

        return [
            'total' => $source->count(),
            'missing' => $this->missingSourceStrings()->count(),
        ];
    }

    public function translate(int $limit = 40, bool $force = false): array
    {
        $limit = max(1, min($limit, 100));
        $summary = [
            'key' => 'platform',
            'label' => 'Platform Interface',
            'processed' => 0,
            'translated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($this->targetLocales() as $locale) {
            $source = $force ? $this->sourceStrings() : $this->missingSourceStrings($locale);
            $items = $source->take($limit);

            if ($items->isEmpty()) {
                continue;
            }

            $payload = $items
                ->values()
                ->mapWithKeys(fn (string $text, int $index): array => ['t'.$index => $text])
                ->all();

            $translated = $this->translator->translateContent($payload, $locale);

            if ($translated === null) {
                $this->translateIndividually($items, $locale, $summary);
                continue;
            }

            foreach ($items->values() as $index => $sourceText) {
                $summary['processed']++;
                $translation = $translated['t'.$index] ?? null;

                if (! is_string($translation) || trim($translation) === '') {
                    $summary['failed']++;
                    continue;
                }

                InterfaceTranslation::updateOrCreate(
                    [
                        'locale' => $locale,
                        'source_hash' => $this->hash($sourceText),
                    ],
                    [
                        'source_text' => $sourceText,
                        'translated_text' => $translation,
                        'provider' => 'openrouter',
                        'model' => $this->translator->model(),
                        'translated_at' => now(),
                    ]
                );

                $summary['translated']++;
            }
        }

        return $summary;
    }

    private function translateIndividually(Collection $items, string $locale, array &$summary): void
    {
        foreach ($items->values() as $sourceText) {
            $summary['processed']++;
            $translation = $this->translator->translateText($sourceText, $locale);

            if (! is_string($translation) || trim($translation) === '') {
                $summary['failed']++;
                $this->recordError($summary, $this->translator->lastFailureMessage() ?: 'Platform interface translation failed.');
                continue;
            }

            InterfaceTranslation::updateOrCreate(
                [
                    'locale' => $locale,
                    'source_hash' => $this->hash($sourceText),
                ],
                [
                    'source_text' => $sourceText,
                    'translated_text' => $translation,
                    'provider' => 'openrouter',
                    'model' => $this->translator->model(),
                    'translated_at' => now(),
                ]
            );

            $summary['translated']++;
        }
    }

    private function recordError(array &$summary, string $message): void
    {
        $message = trim($message);

        if ($message !== '' && ! in_array($message, $summary['errors'], true) && count($summary['errors']) < 5) {
            $summary['errors'][] = $message;
        }
    }

    public function translationsFor(string $locale): array
    {
        if ($locale === (string) config('localization.default', 'en')) {
            return [];
        }

        return InterfaceTranslation::query()
            ->where('locale', $locale)
            ->pluck('translated_text', 'source_text')
            ->all();
    }

    public function sourceStrings(): Collection
    {
        $strings = collect();

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = File::get($file->getPathname());
            $strings = $strings->merge($this->extractStrings($contents));
        }

        return $strings
            ->map(fn (string $text): string => $this->normalize($text))
            ->filter(fn (string $text): bool => $this->isTranslatableText($text))
            ->unique()
            ->sort()
            ->values();
    }

    private function missingSourceStrings(?string $locale = null): Collection
    {
        $locales = $locale ? collect([$locale]) : $this->targetLocales();
        $source = $this->sourceStrings();
        $missing = collect();

        foreach ($locales as $targetLocale) {
            $translated = InterfaceTranslation::query()
                ->where('locale', $targetLocale)
                ->pluck('source_hash')
                ->all();

            $translated = array_flip($translated);

            $missing = $missing->merge(
                $source->reject(fn (string $text): bool => isset($translated[$this->hash($text)]))
            );
        }

        return $missing->unique()->values();
    }

    private function extractStrings(string $contents): Collection
    {
        $contents = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $contents) ?? $contents;
        $contents = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $contents) ?? $contents;
        $contents = preg_replace('/@php\b.*?@endphp/is', ' ', $contents) ?? $contents;

        $matches = [];
        preg_match_all('/>([^<>]+)</u', $contents, $matches);
        $textNodes = collect($matches[1] ?? []);

        $attributeMatches = [];
        preg_match_all('/\b(?:placeholder|aria-label|title|alt)\s*=\s*"([^"{]+)"/u', $contents, $attributeMatches);

        return $textNodes->merge($attributeMatches[1] ?? []);
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function isTranslatableText(string $text): bool
    {
        if ($text === '' || mb_strlen($text) < 2 || mb_strlen($text) > 280) {
            return false;
        }

        if (! preg_match('/[A-Za-z]/', $text)) {
            return false;
        }

        foreach (['{{', '}}', '$', '=>', '::', '@', '<', '>', 'csrf', 'route(', 'asset('] as $needle) {
            if (str_contains($text, $needle)) {
                return false;
            }
        }

        return ! Str::startsWith($text, ['.', '#', 'http', 'data-', '--']);
    }

    private function targetLocales(): Collection
    {
        return collect(config('localization.supported'))
            ->keys()
            ->reject(fn (string $locale): bool => $locale === (string) config('localization.default', 'en'))
            ->values();
    }

    private function hash(string $text): string
    {
        return hash('sha256', $text);
    }
}
