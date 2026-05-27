<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleRevisionNote;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiImageService
{
    public function __construct(
        private readonly AiCostEstimatorService $costs,
        private readonly AiBudgetService $budget,
    ) {
    }

    public function providers(): array
    {
        return collect((array) config('services.ai_image.providers', []))
            ->map(fn (array $config, string $key): array => [
                'key' => $key,
                'label' => (string) ($config['label'] ?? Str::headline($key)),
                'type' => (string) ($config['type'] ?? 'openai_images'),
                'model' => $this->model($key),
                'base_url' => $this->baseUrl($key),
                'size' => $this->size($key),
                'configured' => $this->configured($key),
                'source' => $this->apiKeySource($key),
                'masked_key' => $this->maskedApiKey($key),
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
            'base_url' => $this->baseUrl(),
            'size' => $this->size(),
            'configured' => $this->configured(),
            'source' => $this->apiKeySource(),
            'masked_key' => $this->maskedApiKey(),
            'providers' => $this->providers(),
        ];
    }

    public function provider(): string
    {
        $provider = (string) (Setting::getValue('ai_image.provider') ?: config('services.ai_image.provider', 'openrouter'));

        return array_key_exists($provider, (array) config('services.ai_image.providers', [])) ? $provider : 'openrouter';
    }

    public function providerLabel(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (config("services.ai_image.providers.{$provider}.label") ?: Str::headline($provider));
    }

    public function model(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (
            Setting::getValue("ai_image.{$provider}_model")
            ?: config("services.ai_image.providers.{$provider}.model", '')
        );
    }

    public function baseUrl(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return rtrim((string) (
            Setting::getValue("ai_image.{$provider}_base_url")
            ?: config("services.ai_image.providers.{$provider}.base_url", '')
        ), '/');
    }

    public function size(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (
            Setting::getValue("ai_image.{$provider}_size")
            ?: config("services.ai_image.providers.{$provider}.size", '1024x1024')
        );
    }

    public function configured(?string $provider = null): bool
    {
        $provider ??= $this->provider();

        return $this->apiKey($provider) !== ''
            && $this->model($provider) !== ''
            && $this->baseUrl($provider) !== '';
    }

    public function apiKeySource(?string $provider = null): string
    {
        $provider ??= $this->provider();

        if ((string) config("services.ai_image.providers.{$provider}.key", '') !== '') {
            return 'Environment';
        }

        if ((string) Setting::getValue("ai_image.{$provider}_api_key", '') !== '') {
            return 'Image settings';
        }

        if ((string) Setting::getValue("ai.{$provider}_api_key", '') !== '') {
            return 'AI provider settings';
        }

        if ($provider === 'openrouter') {
            if ((string) config('services.openrouter.key', '') !== '') {
                return 'OPENROUTER_API_KEY';
            }

            if ((string) Setting::getValue('translation.openrouter_api_key', '') !== '') {
                return 'Translation settings';
            }
        }

        return 'Missing';
    }

    public function maskedApiKey(?string $provider = null): string
    {
        $key = $this->apiKey($provider ?? $this->provider());

        return $key === '' ? '' : str_repeat('*', max(strlen($key) - 4, 8)).substr($key, -4);
    }

    public function generatePending(int $limit = 3, ?User $user = null, array $articleIds = [], bool $force = false): array
    {
        $limit = max(1, min(20, $limit));

        $articles = Article::query()
            ->with(['brief', 'categories', 'tags'])
            ->whereNotNull('article_brief_id')
            ->when(! $force, fn ($query) => $query->whereNull('featured_image'))
            ->when($articleIds !== [], fn ($query) => $query->whereIn('id', $articleIds))
            ->whereIn('status', ['draft', 'pending_review', 'revision_requested'])
            ->oldest()
            ->limit($limit)
            ->get();

        $summary = [
            'processed' => 0,
            'created' => 0,
            'failed' => 0,
            'skipped' => 0,
            'articles' => [],
            'errors' => [],
        ];

        foreach ($articles as $article) {
            $summary['processed']++;
            $result = $this->generateForArticle($article, $user, $force);

            if (($result['ok'] ?? false) && isset($result['article'])) {
                if (($result['skipped'] ?? false)) {
                    $summary['skipped']++;
                } else {
                    $summary['created']++;
                }

                $summary['articles'][] = $result['article'];
            } elseif (($result['skipped'] ?? false)) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
                $summary['errors'][] = [
                    'article_id' => $article->id,
                    'message' => $result['message'] ?? 'Image generation failed.',
                ];
            }
        }

        return $summary;
    }

    public function generateForArticle(Article $article, ?User $user = null, bool $force = false): array
    {
        $article->loadMissing(['brief', 'categories', 'tags']);

        if ($article->featured_image && ! $force) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Article already has a featured image.',
                'image_url' => $this->publicImageUrl($article->featured_image),
                'article' => $article,
            ];
        }

        if (! $this->configured()) {
            return ['ok' => false, 'message' => "{$this->providerLabel()} is not configured for image generation."];
        }

        $provider = $this->provider();
        $model = $this->model($provider);
        $prompt = $this->promptForArticle($article);
        $input = [
            'article_id' => $article->id,
            'article_title' => $article->title,
            'prompt' => $prompt,
            'provider' => $provider,
            'model' => $model,
            'size' => $this->size($provider),
        ];

        $generation = AiGeneration::create([
            'feature_key' => 'article_image',
            'source_type' => Article::class,
            'source_id' => $article->id,
            'user_id' => $user?->id,
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => 'article_image_v1',
            'input_hash' => hash('sha256', $this->encode($input)),
            'input_summary' => Str::limit($prompt, 1200, ''),
            'input_payload' => $input,
            'output_language' => 'image',
            'status' => AiGeneration::STATUS_DRAFT,
            'cost_estimate' => 0,
        ]);

        if ($message = $this->budget->blockReason('article_image')) {
            $generation->update([
                'status' => AiGeneration::STATUS_FAILED,
                'error_message' => $message,
            ]);

            return [
                'ok' => false,
                'message' => $message,
                'generation' => $generation->fresh(),
            ];
        }

        try {
            $image = $this->validatedImage($this->generateImageBytes($provider, $model, $prompt));
            $path = $this->storeArticleImage($article, $image['bytes'], $image['mime_type']);
            $imageUrl = $this->publicImageUrl($path);

            if ($force && $article->featured_image && $article->featured_image !== $path) {
                Storage::disk('public')->delete($article->featured_image);
            }

            $article->update([
                'featured_image' => $path,
                'featured_image_caption' => 'AI-generated illustration for this article.',
                'featured_image_credit' => 'AI-generated illustration via '.$this->providerLabel($provider),
                'featured_image_is_ai_generated' => true,
                'featured_image_prompt' => $prompt,
                'featured_image_provider' => $provider,
                'featured_image_model' => $model,
            ]);

            $generation->update([
                'status' => AiGeneration::STATUS_ACCEPTED,
                'output_payload' => [
                    'path' => $path,
                    'url' => $imageUrl,
                    'mime_type' => $image['mime_type'],
                    'provider' => $provider,
                    'model' => $model,
                    'label' => 'AI-generated illustration',
                ],
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
                'cost_estimate' => $this->costs->estimateImage($provider, $model),
            ]);

            $this->createEditorNote($article->fresh(), $user);

            return [
                'ok' => true,
                'message' => 'Image Agent illustration generated.',
                'image_url' => $imageUrl,
                'article' => $article->fresh(['brief', 'categories', 'tags']),
                'generation' => $generation->fresh(),
            ];
        } catch (Throwable $exception) {
            $generation->update([
                'status' => AiGeneration::STATUS_FAILED,
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
            ]);

            return [
                'ok' => false,
                'message' => $exception->getMessage(),
                'generation' => $generation->fresh(),
            ];
        }
    }

    private function generateImageBytes(string $provider, string $model, string $prompt): array
    {
        $type = (string) config("services.ai_image.providers.{$provider}.type", 'openai_images');

        return match ($type) {
            'openrouter_chat_image' => $this->generateWithOpenRouter($provider, $model, $prompt),
            'gemini_generate_content' => $this->generateWithGemini($provider, $model, $prompt),
            'nvidia_nim_infer' => $this->generateWithNvidiaNim($provider, $model, $prompt),
            default => $this->generateWithOpenAiImages($provider, $model, $prompt),
        };
    }

    private function generateWithOpenRouter(string $provider, string $model, string $prompt): array
    {
        $response = Http::withToken($this->apiKey($provider))
            ->withHeaders($this->openRouterHeaders())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl($provider).'/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'modalities' => ['image', 'text'],
                'stream' => false,
                'image_config' => [
                    'image_size' => $this->size($provider),
                ],
            ]);

        $this->throwIfFailed($response, $provider);

        $images = (array) $response->json('choices.0.message.images', []);
        foreach ($images as $image) {
            $url = data_get($image, 'image_url.url') ?: data_get($image, 'imageUrl.url');

            if (is_string($url) && trim($url) !== '') {
                if (Str::startsWith($url, 'data:image/')) {
                    return [
                        'bytes' => $this->decodeBase64($url),
                        'mime_type' => $this->mimeTypeFromDataUrl($url),
                    ];
                }

                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    return $this->downloadImage($url);
                }
            }
        }

        throw new RuntimeException($this->providerLabel($provider).' did not return generated image data.');
    }

    private function generateWithOpenAiImages(string $provider, string $model, string $prompt): array
    {
        $response = Http::withToken($this->apiKey($provider))
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl($provider).'/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'size' => $this->size($provider),
                'n' => 1,
            ]);

        $this->throwIfFailed($response, $provider);

        $base64 = $response->json('data.0.b64_json');
        if (is_string($base64) && trim($base64) !== '') {
            return ['bytes' => $this->decodeBase64($base64), 'mime_type' => 'image/png'];
        }

        $url = $response->json('data.0.url');
        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->downloadImage($url);
        }

        throw new RuntimeException($this->providerLabel($provider).' did not return image bytes or an image URL.');
    }

    private function generateWithGemini(string $provider, string $model, string $prompt): array
    {
        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey($provider)])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl($provider).'/models/'.$model.':generateContent', [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE', 'TEXT'],
                ],
            ]);

        $this->throwIfFailed($response, $provider);

        $parts = (array) $response->json('candidates.0.content.parts', []);
        foreach ($parts as $part) {
            $inline = data_get($part, 'inlineData') ?: data_get($part, 'inline_data');
            $data = is_array($inline) ? ($inline['data'] ?? null) : null;

            if (is_string($data) && trim($data) !== '') {
                return [
                    'bytes' => $this->decodeBase64($data),
                    'mime_type' => (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png'),
                ];
            }
        }

        throw new RuntimeException($this->providerLabel($provider).' did not return inline image data.');
    }

    private function generateWithNvidiaNim(string $provider, string $model, string $prompt): array
    {
        $dimensions = $this->imageDimensions($this->size($provider));

        $response = Http::withToken($this->apiKey($provider))
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->nvidiaNimEndpoint($provider), [
                'prompt' => $prompt,
                'mode' => 'base',
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'samples' => 1,
            ]);

        $this->throwIfFailed($response, $provider);

        $image = $this->extractImageFromPayload((array) $response->json(), [
            'artifacts.0',
            'artifacts.0.base64',
            'artifacts.0.b64_json',
            'artifacts.0.image',
            'artifacts.0.url',
            'data.0',
            'data.0.b64_json',
            'data.0.url',
            'images.0',
            'output.0',
            'image',
            'b64_json',
            'choices.0.message.images.0.image_url.url',
        ]);

        if ($image !== null) {
            return $image;
        }

        throw new RuntimeException($this->providerLabel($provider).' did not return generated image data.');
    }

    private function promptForArticle(Article $article): string
    {
        $storedPrompt = trim((string) $article->featured_image_prompt);
        if ($storedPrompt !== '') {
            return $this->safePrompt($storedPrompt);
        }

        $topics = collect()
            ->merge($article->categories->pluck('name'))
            ->merge($article->tags->pluck('name'))
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        $body = trim(strip_tags((string) ($article->excerpt ?: $article->body)));

        return $this->safePrompt(
            "Editorial illustration for a Life@ local news article.\n"
            .'Headline: '.$article->title."\n"
            .'Topics and places: '.($topics ?: 'Eastern Free State community news')."\n"
            .'Article context: '.Str::limit($body, 900, '')."\n"
            .'Style: tasteful editorial illustration, South African small-town setting, warm natural light, no text in image.'
        );
    }

    private function safePrompt(string $prompt): string
    {
        return Str::limit(trim($prompt)."\n\nSafety rules: Create an illustrative editorial image only. Do not depict identifiable real people, real victims, real officials, accidents, crime scenes, municipal events, or exact news photography. Do not include logos, readable text, watermarks, or fake documentary/photojournalism framing. The image must be suitable to label as \"AI-generated illustration\".", 3600, '');
    }

    private function validatedImage(array $image): array
    {
        $bytes = (string) ($image['bytes'] ?? '');
        $mimeType = $this->detectImageMime($bytes);

        if ($mimeType === '') {
            throw new RuntimeException($this->providerLabel().' returned data, but it was not a displayable image.');
        }

        return [
            'bytes' => $bytes,
            'mime_type' => $mimeType,
        ];
    }

    private function detectImageMime(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        if (function_exists('getimagesizefromstring')) {
            $details = @getimagesizefromstring($bytes);

            if (is_array($details) && isset($details['mime']) && Str::startsWith((string) $details['mime'], 'image/')) {
                return (string) $details['mime'];
            }
        }

        if (Str::startsWith($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        if (Str::startsWith($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (Str::startsWith($bytes, 'GIF87a') || Str::startsWith($bytes, 'GIF89a')) {
            return 'image/gif';
        }

        if (Str::startsWith($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return '';
    }

    private function storeArticleImage(Article $article, string $bytes, string $mimeType): string
    {
        $extension = match (strtolower($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $path = 'articles/ai-generated/'.($article->id ?: 'draft').'-'.Str::slug(Str::limit($article->title, 48, '')).'-'.now()->format('YmdHis').'.'.$extension;
        $disk = Storage::disk('public');
        $directory = dirname($path);

        if ((! $disk->exists($directory) && ! $disk->makeDirectory($directory)) || ! $disk->put($path, $bytes) || ! $disk->exists($path)) {
            throw new RuntimeException('Image Agent generated an image, but storage could not save it.');
        }

        return $path;
    }

    private function publicImageUrl(string $path): string
    {
        return '/media/'.ltrim($path, '/');
    }

    private function downloadImage(string $url): array
    {
        $response = Http::accept('*/*')
            ->timeout($this->timeout())
            ->get($url);

        $this->throwIfFailed($response, 'image_download');

        return [
            'bytes' => $response->body(),
            'mime_type' => (string) ($response->header('Content-Type') ?: 'image/png'),
        ];
    }

    private function decodeBase64(string $base64): string
    {
        if (str_contains($base64, ',')) {
            $base64 = (string) Str::after($base64, ',');
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Image provider returned invalid base64 image data.');
        }

        return $bytes;
    }

    private function nvidiaNimEndpoint(string $provider): string
    {
        $baseUrl = $this->baseUrl($provider);

        if (Str::contains($baseUrl, '/genai/') || Str::endsWith($baseUrl, '/infer')) {
            return $baseUrl;
        }

        return $baseUrl.'/infer';
    }

    private function imageDimensions(string $size): array
    {
        if (preg_match('/(\d+)\s*x\s*(\d+)/i', $size, $matches)) {
            return [
                'width' => max(256, (int) $matches[1]),
                'height' => max(256, (int) $matches[2]),
            ];
        }

        return ['width' => 1024, 'height' => 1024];
    }

    private function extractImageFromPayload(array $payload, array $paths): ?array
    {
        foreach ($paths as $path) {
            $image = $this->normaliseImageValue(data_get($payload, $path));

            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    private function normaliseImageValue(mixed $value): ?array
    {
        if (is_array($value)) {
            $mimeType = (string) ($value['mime_type'] ?? $value['mimeType'] ?? 'image/png');

            foreach (['base64', 'b64_json', 'image', 'url'] as $key) {
                $image = is_string($value[$key] ?? null)
                    ? $this->normaliseImageString((string) $value[$key], $mimeType)
                    : $this->normaliseImageValue($value[$key] ?? null);

                if ($image !== null) {
                    return $image;
                }
            }

            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->normaliseImageString($value);
    }

    private function normaliseImageString(string $value, string $mimeType = 'image/png'): ?array
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, 'data:image/')) {
            return [
                'bytes' => $this->decodeBase64($value),
                'mime_type' => $this->mimeTypeFromDataUrl($value),
            ];
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $this->downloadImage($value);
        }

        return ['bytes' => $this->decodeBase64($value), 'mime_type' => $mimeType ?: 'image/png'];
    }

    private function createEditorNote(Article $article, ?User $user): void
    {
        if (! $user) {
            return;
        }

        ArticleRevisionNote::create([
            'article_id' => $article->id,
            'author_user_id' => $user->id,
            'status' => $article->status,
            'note' => 'Image Agent generated an AI illustration. Keep the public label visible and do not present it as real news photography.',
        ]);
    }

    private function apiKey(string $provider): string
    {
        $value = (string) (
            config("services.ai_image.providers.{$provider}.key")
            ?: Setting::getValue("ai_image.{$provider}_api_key", '')
            ?: Setting::getValue("ai.{$provider}_api_key", '')
        );

        if ($provider === 'openrouter' && $value === '') {
            $value = (string) (config('services.openrouter.key') ?: Setting::getValue('translation.openrouter_api_key', ''));
        }

        return Str::startsWith(trim($value), 'Bearer ') ? trim(substr(trim($value), 7)) : trim($value);
    }

    private function openRouterHeaders(): array
    {
        return [
            'HTTP-Referer' => (string) config('app.url'),
            'X-OpenRouter-Title' => (string) config('app.name', 'Life Platform'),
        ];
    }

    private function mimeTypeFromDataUrl(string $url): string
    {
        if (preg_match('/^data:([^;]+);base64,/', $url, $matches)) {
            return (string) $matches[1];
        }

        return 'image/png';
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

        $label = $provider === 'image_download' ? 'Image download' : $this->providerLabel($provider);

        throw new RuntimeException($label.' returned '.$response->status().': '.Str::limit(trim((string) $message), 300));
    }

    private function timeout(): int
    {
        return max(15, (int) config('services.ai_image.timeout', 120));
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
