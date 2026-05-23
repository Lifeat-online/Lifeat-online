<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiGatewayService
{
    public function __construct(
        private readonly AiCostEstimatorService $costs,
        private readonly AiBudgetService $budget,
    ) {
    }

    public function providers(): array
    {
        return collect((array) config('services.ai.providers', []))
            ->map(fn (array $config, string $key): array => [
                'key' => $key,
                'label' => (string) ($config['label'] ?? Str::headline($key)),
                'type' => (string) ($config['type'] ?? 'openai_compatible'),
                'model' => $this->model($key),
                'configured' => $this->configured($key),
                'source' => $this->apiKeySource($key),
                'masked_key' => $this->maskedApiKey($key),
                'base_url' => $this->baseUrl($key),
                'key_optional' => (bool) ($config['key_optional'] ?? false),
            ])
            ->values()
            ->all();
    }

    public function status(): array
    {
        return [
            'provider' => $this->provider(),
            'provider_label' => $this->providerLabel($this->provider()),
            'model' => $this->model(),
            'configured' => $this->configured(),
            'source' => $this->apiKeySource(),
            'masked_key' => $this->maskedApiKey(),
            'providers' => $this->providers(),
        ];
    }

    public function provider(): string
    {
        $provider = (string) (Setting::getValue('ai.provider') ?: config('services.ai.provider', 'openrouter'));

        return array_key_exists($provider, (array) config('services.ai.providers', [])) ? $provider : 'openrouter';
    }

    public function providerLabel(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (config("services.ai.providers.{$provider}.label") ?: Str::headline($provider));
    }

    public function model(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (Setting::getValue("ai.{$provider}_model") ?: config("services.ai.providers.{$provider}.model", ''));
    }

    public function baseUrl(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return rtrim((string) (Setting::getValue("ai.{$provider}_base_url") ?: config("services.ai.providers.{$provider}.base_url", '')), '/');
    }

    public function configured(?string $provider = null): bool
    {
        $provider ??= $this->provider();

        if ((bool) config("services.ai.providers.{$provider}.key_optional", false)) {
            return $this->baseUrl($provider) !== '' && $this->model($provider) !== '';
        }

        if ($provider === 'azure_openai') {
            return $this->apiKey($provider) !== '' && $this->baseUrl($provider) !== '' && $this->model($provider) !== '';
        }

        return $this->apiKey($provider) !== '' && $this->model($provider) !== '';
    }

    public function apiKeySource(?string $provider = null): string
    {
        $provider ??= $this->provider();

        if ((string) config("services.ai.providers.{$provider}.key", '') !== '') {
            return 'Environment';
        }

        if ((string) Setting::getValue("ai.{$provider}_api_key", '') !== '') {
            return 'Settings';
        }

        if ($provider === 'openrouter') {
            if ((string) config('services.openrouter.key', '') !== '') {
                return 'OPENROUTER_API_KEY';
            }

            if ((string) Setting::getValue('translation.openrouter_api_key', '') !== '') {
                return 'Translation settings';
            }
        }

        return (bool) config("services.ai.providers.{$provider}.key_optional", false) ? 'Not required' : 'Missing';
    }

    public function maskedApiKey(?string $provider = null): string
    {
        $key = $this->apiKey($provider ?? $this->provider());

        if ($key === '') {
            return '';
        }

        return str_repeat('*', max(strlen($key) - 4, 8)).substr($key, -4);
    }

    public function generateStructured(
        string $featureKey,
        string $promptVersion,
        string $systemPrompt,
        array $input,
        ?Model $source = null,
        ?User $user = null,
        ?string $outputLanguage = null,
    ): array {
        $provider = $this->provider();
        $model = $this->model($provider);
        $encodedInput = $this->encode($input);
        $inputTokens = $this->estimateTokens($systemPrompt.' '.$encodedInput);

        $generation = AiGeneration::create([
            'feature_key' => $featureKey,
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->getKey(),
            'user_id' => $user?->id,
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'input_hash' => hash('sha256', $encodedInput),
            'input_summary' => Str::limit($this->inputSummary($input), 1200),
            'input_payload' => $input,
            'output_language' => $outputLanguage,
            'status' => AiGeneration::STATUS_DRAFT,
            'token_input_estimate' => $inputTokens,
            'cost_estimate' => 0,
        ]);

        if (! $this->configured($provider)) {
            $message = "{$this->providerLabel($provider)} is not configured.";
            $generation->update([
                'status' => AiGeneration::STATUS_FAILED,
                'error_message' => $message,
            ]);

            return ['ok' => false, 'message' => $message, 'generation' => $generation];
        }

        if ($message = $this->budget->blockReason($featureKey)) {
            $generation->update([
                'status' => AiGeneration::STATUS_FAILED,
                'error_message' => $message,
            ]);

            return ['ok' => false, 'message' => $message, 'generation' => $generation->fresh()];
        }

        try {
            $responseText = $this->send($provider, $model, $systemPrompt, $input);
            $payload = $this->decodeJsonResponse($responseText);

            if (! is_array($payload)) {
                $message = 'AI provider returned text, but not valid JSON.';
                $outputTokens = $this->estimateTokens($responseText);

                $generation->update([
                    'status' => AiGeneration::STATUS_FAILED,
                    'error_message' => $message.' Preview: '.Str::limit(trim($responseText), 220),
                    'token_output_estimate' => $outputTokens,
                    'cost_estimate' => $this->costs->estimateText($provider, $model, $inputTokens, $outputTokens),
                ]);

                return ['ok' => false, 'message' => $message, 'generation' => $generation];
            }

            $outputTokens = $this->estimateTokens($this->encode($payload));

            $generation->update([
                'output_payload' => $payload,
                'token_output_estimate' => $outputTokens,
                'cost_estimate' => $this->costs->estimateText($provider, $model, $inputTokens, $outputTokens),
            ]);

            return [
                'ok' => true,
                'message' => 'AI suggestion generated.',
                'payload' => $payload,
                'generation' => $generation->fresh(),
            ];
        } catch (Throwable $exception) {
            $message = $exception->getMessage();

            Log::warning('AI generation failed.', [
                'feature' => $featureKey,
                'provider' => $provider,
                'model' => $model,
                'message' => $message,
            ]);

            $generation->update([
                'status' => AiGeneration::STATUS_FAILED,
                'error_message' => $message,
            ]);

            return ['ok' => false, 'message' => $message, 'generation' => $generation->fresh()];
        }
    }

    private function send(string $provider, string $model, string $systemPrompt, array $input): string
    {
        $type = (string) config("services.ai.providers.{$provider}.type", 'openai_compatible');

        return match ($type) {
            'anthropic' => $this->sendAnthropic($provider, $model, $systemPrompt, $input),
            'gemini' => $this->sendGemini($provider, $model, $systemPrompt, $input),
            'azure_openai' => $this->sendAzureOpenAi($provider, $model, $systemPrompt, $input),
            'cohere' => $this->sendCohere($provider, $model, $systemPrompt, $input),
            default => $this->sendOpenAiCompatible($provider, $model, $systemPrompt, $input),
        };
    }

    private function sendOpenAiCompatible(string $provider, string $model, string $systemPrompt, array $input): string
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->withHeaders($this->openAiCompatibleHeaders($provider));

        $apiKey = $this->apiKey($provider);
        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt."\nReturn only one valid JSON object."],
                ['role' => 'user', 'content' => $this->encode($input)],
            ],
            'temperature' => $this->temperature(),
            'max_tokens' => $this->maxTokens(),
        ];

        if (in_array($provider, ['openai', 'openrouter', 'groq', 'xai'], true)) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $request
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl($provider).'/chat/completions', $payload);

        $this->throwIfFailed($response, $provider);

        return (string) ($response->json('choices.0.message.content') ?: '');
    }

    private function sendAnthropic(string $provider, string $model, string $systemPrompt, array $input): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey($provider),
            'anthropic-version' => (string) config("services.ai.providers.{$provider}.version", '2023-06-01'),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl($provider).'/messages', [
                'model' => $model,
                'system' => $systemPrompt."\nReturn only one valid JSON object.",
                'max_tokens' => $this->maxTokens(),
                'temperature' => $this->temperature(),
                'messages' => [
                    ['role' => 'user', 'content' => $this->encode($input)],
                ],
            ]);

        $this->throwIfFailed($response, $provider);

        return (string) ($response->json('content.0.text') ?: '');
    }

    private function sendGemini(string $provider, string $model, string $systemPrompt, array $input): string
    {
        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey($provider),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl($provider).'/models/'.$model.':generateContent', [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt."\nReturn only one valid JSON object."],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $this->encode($input)],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $this->temperature(),
                    'maxOutputTokens' => $this->maxTokens(),
                    'responseMimeType' => 'application/json',
                ],
            ]);

        $this->throwIfFailed($response, $provider);

        return (string) ($response->json('candidates.0.content.parts.0.text') ?: '');
    }

    private function sendAzureOpenAi(string $provider, string $model, string $systemPrompt, array $input): string
    {
        $endpoint = $this->baseUrl($provider);
        $apiVersion = (string) (Setting::getValue('ai.azure_openai_api_version') ?: config("services.ai.providers.{$provider}.api_version", '2024-10-21'));
        $url = $endpoint.'/openai/deployments/'.$model.'/chat/completions?'.http_build_query(['api-version' => $apiVersion]);

        $response = Http::withHeaders(['api-key' => $this->apiKey($provider)])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($url, [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt."\nReturn only one valid JSON object."],
                    ['role' => 'user', 'content' => $this->encode($input)],
                ],
                'temperature' => $this->temperature(),
                'max_tokens' => $this->maxTokens(),
                'response_format' => ['type' => 'json_object'],
            ]);

        $this->throwIfFailed($response, $provider);

        return (string) ($response->json('choices.0.message.content') ?: '');
    }

    private function sendCohere(string $provider, string $model, string $systemPrompt, array $input): string
    {
        $response = Http::withToken($this->apiKey($provider))
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl($provider).'/chat', [
                'model' => $model,
                'temperature' => $this->temperature(),
                'max_tokens' => $this->maxTokens(),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt."\nReturn only one valid JSON object."],
                    ['role' => 'user', 'content' => $this->encode($input)],
                ],
            ]);

        $this->throwIfFailed($response, $provider);

        $message = $response->json('message.content.0.text')
            ?: $response->json('message.content.0.text.value')
            ?: $response->json('text');

        return (string) $message;
    }

    private function apiKey(string $provider): string
    {
        $value = (string) (config("services.ai.providers.{$provider}.key") ?: Setting::getValue("ai.{$provider}_api_key", ''));

        if ($provider === 'openrouter' && $value === '') {
            $value = (string) (config('services.openrouter.key') ?: Setting::getValue('translation.openrouter_api_key', ''));
        }

        return $this->cleanSecret($value);
    }

    private function openAiCompatibleHeaders(string $provider): array
    {
        if ($provider !== 'openrouter') {
            return [];
        }

        return [
            'HTTP-Referer' => (string) config('app.url'),
            'X-OpenRouter-Title' => (string) config('app.name', 'Life Platform'),
        ];
    }

    private function throwIfFailed(Response $response, string $provider): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message')
            ?: $response->json('message')
            ?: $response->json('error')
            ?: $response->body();

        throw new \RuntimeException($this->providerLabel($provider).' returned '.$response->status().': '.Str::limit(trim((string) $message), 300));
    }

    private function decodeJsonResponse(string $message): ?array
    {
        $message = trim($message);

        if ($message === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $message, $matches)) {
            $message = trim($matches[1]);
        } elseif (! str_starts_with($message, '{')) {
            $extracted = $this->extractFirstJsonObject($message);
            $message = $extracted ?: $message;
        }

        $decoded = json_decode($message, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
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

    private function cleanSecret(string $value): string
    {
        $value = trim($value, " \t\n\r\0\x0B\"'");

        return Str::startsWith($value, 'Bearer ') ? trim(substr($value, 7)) : $value;
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.ai.timeout', 90));
    }

    private function maxTokens(): int
    {
        return max(128, (int) config('services.ai.max_tokens', 2048));
    }

    private function temperature(): float
    {
        return (float) config('services.ai.temperature', 0.2);
    }

    private function inputSummary(array $input): string
    {
        return collect(Arr::dot($input))
            ->map(fn ($value, string $key): string => $key.': '.Str::limit(is_scalar($value) ? (string) $value : $this->encode($value), 180))
            ->implode("\n");
    }

    private function estimateTokens(string $text): int
    {
        $characters = mb_strlen($text);

        return max(1, (int) ceil($characters / 4));
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
