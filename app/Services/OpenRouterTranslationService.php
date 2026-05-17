<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenRouterTranslationService
{
    public function translateModel(Model $model, string $targetLocale = 'af', bool $force = false): array
    {
        if (! method_exists($model, 'translatableContent') || ! method_exists($model, 'contentTranslations')) {
            return ['ok' => false, 'message' => 'Model is not translatable.'];
        }

        $source = $model->translatableContent();

        if ($source === []) {
            return ['ok' => false, 'message' => 'No source content available to translate.'];
        }

        $sourceHash = $model->contentSourceHash();
        $existing = $model->contentTranslations()->where('locale', $targetLocale)->first();

        if (! $force && $existing && $existing->source_hash === $sourceHash) {
            return ['ok' => true, 'message' => 'Translation is already current.', 'translation' => $existing];
        }

        $translated = $this->translateContent($source, $targetLocale);

        if ($translated === null) {
            return ['ok' => false, 'message' => 'Translation provider is not configured or did not return usable content.'];
        }

        $translation = $model->contentTranslations()->updateOrCreate(
            ['locale' => $targetLocale],
            [
                'content' => $translated,
                'source_locale' => method_exists($model, 'sourceLocale')
                    ? $model->sourceLocale()
                    : (string) config('localization.default', 'en'),
                'source_hash' => $sourceHash,
                'provider' => 'openrouter',
                'model' => $this->model(),
                'translated_at' => now(),
            ]
        );

        return ['ok' => true, 'message' => 'Translation saved.', 'translation' => $translation];
    }

    public function translateText(string $text, string $targetLocale = 'af'): ?string
    {
        $result = $this->translateContent(['text' => $text], $targetLocale);

        return is_array($result) ? ($result['text'] ?? null) : null;
    }

    public function translateContent(array $content, string $targetLocale = 'af'): ?array
    {
        $apiKey = (string) config('services.openrouter.key');

        if ($apiKey === '') {
            return null;
        }

        $content = collect($content)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->all();

        if ($content === []) {
            return [];
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(60)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $this->model(),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($targetLocale),
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    ],
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('OpenRouter translation failed.', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $message = $response->json('choices.0.message.content');

            if (! is_string($message) || trim($message) === '') {
                return null;
            }

            $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return null;
            }

            return collect($content)
                ->mapWithKeys(fn ($value, $key): array => [$key => is_string($decoded[$key] ?? null) ? $decoded[$key] : $value])
                ->all();
        } catch (Throwable $exception) {
            Log::warning('OpenRouter translation exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function model(): string
    {
        return (string) config('services.openrouter.model', 'google/gemma-4-31b-it:free');
    }

    private function systemPrompt(string $targetLocale): string
    {
        $language = config("localization.supported.{$targetLocale}.name", $targetLocale);

        return "Translate the provided JSON values into {$language}. Keep keys exactly the same. Preserve names, places, URLs, email addresses, phone numbers, HTML tags, markdown, and paragraph breaks. Return only one valid JSON object.";
    }
}
