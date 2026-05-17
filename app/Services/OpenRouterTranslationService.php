<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenRouterTranslationService
{
    private ?string $lastFailureMessage = null;
    private ?int $lastFailureStatus = null;

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
            return [
                'ok' => false,
                'message' => $this->lastFailureMessage()
                    ?: 'Translation provider is not configured or did not return usable content.',
            ];
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
        $this->lastFailureMessage = null;
        $this->lastFailureStatus = null;
        $apiKey = $this->apiKey();

        if ($apiKey === '') {
            $this->lastFailureMessage = 'OpenRouter API key is missing.';

            return null;
        }

        $content = collect($content)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->all();

        if ($content === []) {
            return [];
        }

        try {
            $response = $this->sendTranslationRequest($apiKey, $content, $targetLocale, true);

            if ($this->shouldFallbackToJsonMode($response)) {
                $response = $this->sendTranslationRequest($apiKey, $content, $targetLocale, false);
            }

            if (! $response->successful()) {
                $this->lastFailureStatus = $response->status();
                $this->lastFailureMessage = 'OpenRouter returned '.$response->status().': '.$this->providerErrorMessage($response);

                Log::warning('OpenRouter translation failed.', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $message = $response->json('choices.0.message.content');

            if (! is_string($message) || trim($message) === '') {
                $this->lastFailureMessage = 'OpenRouter response did not include translated content.';

                return null;
            }

            $decoded = $this->decodeJsonResponse($message);

            if (! is_array($decoded)) {
                $this->lastFailureMessage ??= 'OpenRouter response was not a JSON object.';

                return null;
            }

            return collect($content)
                ->mapWithKeys(fn ($value, $key): array => [$key => is_string($decoded[$key] ?? null) ? $decoded[$key] : $value])
                ->all();
        } catch (Throwable $exception) {
            $this->lastFailureMessage = 'OpenRouter translation error: '.$exception->getMessage();

            Log::warning('OpenRouter translation exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function lastFailureMessage(): ?string
    {
        return $this->lastFailureMessage;
    }

    public function lastFailureStatus(): ?int
    {
        return $this->lastFailureStatus;
    }

    public function wasRateLimited(): bool
    {
        return $this->lastFailureStatus === 429;
    }

    public function model(): string
    {
        return (string) (Setting::getValue('translation.openrouter_model') ?: config('services.openrouter.model', 'google/gemma-4-31b-it:free'));
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function apiKeySource(): string
    {
        if ((string) config('services.openrouter.key') !== '') {
            return 'Environment';
        }

        if ((string) Setting::getValue('translation.openrouter_api_key', '') !== '') {
            return 'Settings';
        }

        return 'Missing';
    }

    public function maskedApiKey(): string
    {
        $apiKey = $this->apiKey();

        if ($apiKey === '') {
            return '';
        }

        return str_repeat('*', max(strlen($apiKey) - 4, 8)).substr($apiKey, -4);
    }

    private function apiKey(): string
    {
        return (string) (config('services.openrouter.key') ?: Setting::getValue('translation.openrouter_api_key', ''));
    }

    private function sendTranslationRequest(string $apiKey, array $content, string $targetLocale, bool $structured): Response
    {
        return Http::withToken($apiKey)
            ->withHeaders($this->headers())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.openrouter.timeout', 90))
            ->post($this->endpoint(), $this->payload($content, $targetLocale, $structured));
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/').'/chat/completions';
    }

    private function headers(): array
    {
        return [
            'HTTP-Referer' => (string) config('app.url'),
            'X-OpenRouter-Title' => (string) config('app.name', 'Life Platform'),
        ];
    }

    private function payload(array $content, string $targetLocale, bool $structured): array
    {
        $payload = [
            'model' => $this->model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt($targetLocale),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'target_locale' => $targetLocale,
                        'source' => $content,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
            'temperature' => 0.1,
            'max_completion_tokens' => (int) config('services.openrouter.max_tokens', 4096),
        ];

        if ($structured && filter_var(config('services.openrouter.structured_outputs', true), FILTER_VALIDATE_BOOL)) {
            $payload['structured_outputs'] = true;
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'platform_translation',
                    'strict' => true,
                    'schema' => $this->jsonSchemaFor($content),
                ],
            ];

            return $payload;
        }

        $payload['response_format'] = ['type' => 'json_object'];

        return $payload;
    }

    private function jsonSchemaFor(array $content): array
    {
        $properties = collect($content)
            ->mapWithKeys(fn ($value, string $key): array => [
                $key => [
                    'type' => 'string',
                    'description' => "Translated value for {$key}",
                ],
            ])
            ->all();

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($content),
            'additionalProperties' => false,
        ];
    }

    private function shouldFallbackToJsonMode(Response $response): bool
    {
        if ($response->successful() || ! in_array($response->status(), [400, 422], true)) {
            return false;
        }

        return str_contains($response->body(), 'response_format')
            || str_contains($response->body(), 'structured')
            || str_contains($response->body(), 'schema');
    }

    private function normalizeJsonResponse(string $message): string
    {
        $message = trim($message);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $message, $matches)) {
            return trim($matches[1]);
        }

        if ($this->looksLikeJsonObject($message)) {
            return $message;
        }

        $object = $this->extractFirstJsonObject($message);

        return $object ?? $message;
    }

    private function decodeJsonResponse(string $message): ?array
    {
        $normalized = $this->normalizeJsonResponse($message);
        $decoded = json_decode($normalized, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $this->lastFailureMessage = 'OpenRouter returned invalid JSON: '.json_last_error_msg().'. Response preview: '.mb_substr(trim($message), 0, 180);

        return null;
    }

    private function looksLikeJsonObject(string $message): bool
    {
        return str_starts_with(trim($message), '{') && str_ends_with(trim($message), '}');
    }

    private function extractFirstJsonObject(string $message): ?string
    {
        $length = strlen($message);
        $start = strpos($message, '{');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($index = $start; $index < $length; $index++) {
            $char = $message[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($message, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    private function providerErrorMessage(Response $response): string
    {
        $message = $response->json('error.message')
            ?: $response->json('message')
            ?: $response->body();

        $message = trim((string) $message);

        return $message === ''
            ? 'Provider returned an empty error response.'
            : mb_substr($message, 0, 240);
    }

    private function systemPrompt(string $targetLocale): string
    {
        $language = config("localization.supported.{$targetLocale}.name", $targetLocale);

        return "You are the Life Platform translation engine. Translate only the values in the provided source object into {$language}. Keep keys exactly the same. Preserve names, places, URLs, email addresses, phone numbers, HTML tags, markdown, placeholders, currency values, and paragraph breaks. Return only one valid JSON object whose top-level keys exactly match the source keys.";
    }
}
