<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\Setting;
use App\Services\AiGatewayService;
use App\Services\AiImageService;
use App\Services\EditorialBriefService;
use App\Services\VoiceGatewayService;
use App\Services\JimmyWritingService;
use App\Services\Research\ResearchCollectorService;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AiSettingsController extends Controller
{
    public function save(Request $request, AiGatewayService $gateway, AiImageService $images, VoiceGatewayService $voice): JsonResponse|RedirectResponse
    {
        $this->ensureDevOwner($request);

        $providers = collect($gateway->providers())->pluck('key')->all();
        $imageProviders = collect($images->providers())->pluck('key')->all();
        $voiceProviders = collect($voice->providers())->pluck('key')->all();

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($providers)],
            'image_provider' => ['nullable', 'string', Rule::in($imageProviders)],
            'voice_provider' => ['nullable', 'string', Rule::in($voiceProviders)],
            'fallback_providers' => ['nullable', 'string', 'max:1000'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['nullable', 'string', 'max:1000'],
            'models' => ['nullable', 'array'],
            'models.*' => ['nullable', 'string', 'max:255'],
            'fallback_models' => ['nullable', 'array'],
            'fallback_models.*' => ['nullable', 'string', 'max:1000'],
            'base_urls' => ['nullable', 'array'],
            'base_urls.*' => ['nullable', 'string', 'max:500'],
            'image_keys' => ['nullable', 'array'],
            'image_keys.*' => ['nullable', 'string', 'max:1000'],
            'image_models' => ['nullable', 'array'],
            'image_models.*' => ['nullable', 'string', 'max:255'],
            'image_fallback_models' => ['nullable', 'array'],
            'image_fallback_models.*' => ['nullable', 'string', 'max:1000'],
            'image_base_urls' => ['nullable', 'array'],
            'image_base_urls.*' => ['nullable', 'string', 'max:500'],
            'image_sizes' => ['nullable', 'array'],
            'image_sizes.*' => ['nullable', 'string', 'max:80'],
            'voice_key' => ['nullable', 'string', 'max:1000'],
            'voice_voice_id' => ['nullable', 'string', 'max:255'],
            'voice_english_model' => ['nullable', 'string', 'max:255'],
            'voice_afrikaans_model' => ['nullable', 'string', 'max:255'],
            'voice_base_url' => ['nullable', 'string', 'max:500'],
            'voice_output_format' => ['nullable', 'string', 'max:80'],
            'voice_keys' => ['nullable', 'array'],
            'voice_keys.*' => ['nullable', 'string', 'max:1000'],
            'voice_voice_ids' => ['nullable', 'array'],
            'voice_voice_ids.*' => ['nullable', 'string', 'max:255'],
            'voice_english_models' => ['nullable', 'array'],
            'voice_english_models.*' => ['nullable', 'string', 'max:255'],
            'voice_afrikaans_models' => ['nullable', 'array'],
            'voice_afrikaans_models.*' => ['nullable', 'string', 'max:255'],
            'voice_base_urls' => ['nullable', 'array'],
            'voice_base_urls.*' => ['nullable', 'string', 'max:500'],
            'voice_output_formats' => ['nullable', 'array'],
            'voice_output_formats.*' => ['nullable', 'string', 'max:80'],
            'azure_openai_api_version' => ['nullable', 'string', 'max:80'],
        ]);

        $this->setSetting($request, 'ai.provider', $validated['provider'], 'string');
        if (array_key_exists('fallback_providers', $validated)) {
            $this->setSetting($request, 'ai.fallback_providers', $this->normaliseCsvSetting((string) $validated['fallback_providers']), 'string');
        }
        if (filled($validated['image_provider'] ?? null)) {
            $this->setSetting($request, 'ai_image.provider', $validated['image_provider'], 'string');
        }
        if (filled($validated['voice_provider'] ?? null)) {
            $this->setSetting($request, 'voice.provider', $validated['voice_provider'], 'string');
        }

        foreach ($providers as $provider) {
            $key = trim((string) data_get($validated, "keys.{$provider}", ''));
            $model = trim((string) data_get($validated, "models.{$provider}", ''));
            $fallbackModels = trim((string) data_get($validated, "fallback_models.{$provider}", ''));
            $baseUrl = trim((string) data_get($validated, "base_urls.{$provider}", ''));

            if ($key !== '') {
                $this->setSetting($request, "ai.{$provider}_api_key", $key, 'secret');
            }

            if ($model !== '') {
                $this->setSetting($request, "ai.{$provider}_model", $model, 'string');
            }

            if (array_key_exists($provider, (array) ($validated['fallback_models'] ?? []))) {
                $this->setSetting($request, "ai.{$provider}_fallback_models", $this->normaliseCsvSetting($fallbackModels), 'string');
            }

            if ($baseUrl !== '') {
                $this->setSetting($request, "ai.{$provider}_base_url", rtrim($baseUrl, '/'), 'string');
            }
        }

        foreach ($imageProviders as $provider) {
            $key = trim((string) data_get($validated, "image_keys.{$provider}", ''));
            $model = trim((string) data_get($validated, "image_models.{$provider}", ''));
            $fallbackModels = trim((string) data_get($validated, "image_fallback_models.{$provider}", ''));
            $baseUrl = trim((string) data_get($validated, "image_base_urls.{$provider}", ''));
            $size = trim((string) data_get($validated, "image_sizes.{$provider}", ''));

            if ($key !== '') {
                $this->setSetting($request, "ai_image.{$provider}_api_key", $key, 'secret');
            }

            if ($model !== '') {
                $this->setSetting($request, "ai_image.{$provider}_model", $model, 'string');
            }

            if (array_key_exists($provider, (array) ($validated['image_fallback_models'] ?? []))) {
                $this->setSetting($request, "ai_image.{$provider}_fallback_models", $this->normaliseCsvSetting($fallbackModels), 'string');
            }

            if ($baseUrl !== '') {
                $this->setSetting($request, "ai_image.{$provider}_base_url", rtrim($baseUrl, '/'), 'string');
            }

            if ($size !== '') {
                $this->setSetting($request, "ai_image.{$provider}_size", $size, 'string');
            }
        }

        if (filled($validated['azure_openai_api_version'] ?? null)) {
            $this->setSetting($request, 'ai.azure_openai_api_version', trim($validated['azure_openai_api_version']), 'string');
        }

        foreach ($voiceProviders as $provider) {
            $key = trim((string) data_get($validated, "voice_keys.{$provider}", ''));
            $voiceId = trim((string) data_get($validated, "voice_voice_ids.{$provider}", ''));
            $englishModel = trim((string) data_get($validated, "voice_english_models.{$provider}", ''));
            $afrikaansModel = trim((string) data_get($validated, "voice_afrikaans_models.{$provider}", ''));
            $baseUrl = trim((string) data_get($validated, "voice_base_urls.{$provider}", ''));
            $outputFormat = trim((string) data_get($validated, "voice_output_formats.{$provider}", ''));

            if ($key !== '') {
                $this->setSetting($request, "voice.{$provider}_api_key", $key, 'secret');
            }

            if ($voiceId !== '') {
                $this->setSetting($request, "voice.{$provider}_voice_id", $voiceId, 'string');
            }

            if ($englishModel !== '') {
                $this->setSetting($request, "voice.{$provider}_english_model", $englishModel, 'string');
            }

            if ($afrikaansModel !== '') {
                $this->setSetting($request, "voice.{$provider}_afrikaans_model", $afrikaansModel, 'string');
            }

            if ($baseUrl !== '') {
                $this->setSetting($request, "voice.{$provider}_base_url", rtrim($baseUrl, '/'), 'string');
            }

            if ($outputFormat !== '') {
                $this->setSetting($request, "voice.{$provider}_output_format", $outputFormat, 'string');
            }
        }

        $voiceProvider = filled($validated['voice_provider'] ?? null) ? $validated['voice_provider'] : $voice->provider();
        $voiceKey = trim((string) ($validated['voice_key'] ?? ''));
        $voiceVoiceId = trim((string) ($validated['voice_voice_id'] ?? ''));
        $voiceEnglishModel = trim((string) ($validated['voice_english_model'] ?? ''));
        $voiceAfrikaansModel = trim((string) ($validated['voice_afrikaans_model'] ?? ''));
        $voiceBaseUrl = trim((string) ($validated['voice_base_url'] ?? ''));
        $voiceOutputFormat = trim((string) ($validated['voice_output_format'] ?? ''));

        if ($voiceKey !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_api_key", $voiceKey, 'secret');
        }

        if ($voiceVoiceId !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_voice_id", $voiceVoiceId, 'string');
        }

        if ($voiceEnglishModel !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_english_model", $voiceEnglishModel, 'string');
        }

        if ($voiceAfrikaansModel !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_afrikaans_model", $voiceAfrikaansModel, 'string');
        }

        if ($voiceBaseUrl !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_base_url", rtrim($voiceBaseUrl, '/'), 'string');
        }

        if ($voiceOutputFormat !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_output_format", $voiceOutputFormat, 'string');
        }

        $payload = [
            'ok' => true,
            'message' => 'AI provider settings saved.',
            'status' => $gateway->status(),
            'image_status' => $images->status(),
            'voice_status' => $voice->status(),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('status', $payload['message']);
    }

    public function test(Request $request, AiGatewayService $gateway, AiPromptCatalog $prompts): JsonResponse|RedirectResponse
    {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'prompt' => ['nullable', 'string', 'max:1000'],
        ]);

        $prompt = $prompts->get('settings_test');
        $result = $gateway->generateStructured(
            'settings_test',
            $prompt['version'],
            $prompt['system'],
            [
                'prompt' => trim((string) ($validated['prompt'] ?? 'Say that the Life@ AI provider is ready.')),
                'schema' => $prompt['schema'],
            ],
            null,
            $request->user(),
            'en',
        );

        if ($request->expectsJson()) {
            return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
        }

        $message = ($result['ok'] ?? false)
            ? 'AI test passed: '.(string) data_get($result, 'payload.summary', 'Provider returned a valid response.')
            : 'AI test failed: '.($result['message'] ?? 'Provider unavailable.');

        return back()->with('status', $message);
    }

    public function writerProcess(
        Request $request,
        ResearchCollectorService $collector,
        EditorialBriefService $briefs,
        JimmyWritingService $jimmy,
        AiImageService $images,
    ): JsonResponse {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['collect', 'brief', 'write', 'images', 'all'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'source' => ['nullable', 'string', 'max:500'],
            'seed_sources' => ['nullable', 'boolean'],
        ]);

        $action = $validated['action'];
        $limit = (int) ($validated['limit'] ?? 3);
        $sourceSlugs = $this->sourceSlugs((string) ($validated['source'] ?? ''));
        $summaries = [];

        if (in_array($action, ['collect', 'all'], true) && (($validated['seed_sources'] ?? false) || ResearchSource::query()->count() === 0)) {
            $summaries['seed'] = $collector->seedDefaultSources();
        }

        if (in_array($action, ['collect', 'all'], true)) {
            $summaries['collect'] = $this->summarizeCollector($collector->collect($sourceSlugs, $limit));
        }

        if (in_array($action, ['brief', 'all'], true)) {
            $summaries['brief'] = $this->summarizeAiBatch($briefs->generatePending($limit, $request->user()));
        }

        if (in_array($action, ['write', 'all'], true)) {
            $summaries['write'] = $this->summarizeAiBatch($jimmy->draftApproved($limit, $request->user()));
        }

        if (in_array($action, ['images', 'all'], true)) {
            $summaries['images'] = $this->summarizeAiBatch($images->generatePending($limit, $request->user()));
        }

        return response()->json([
            'ok' => true,
            'message' => $this->writerProcessMessage($action, $summaries),
            'action' => $action,
            'summaries' => $summaries,
            'status' => $this->writerProcessStatus(),
        ]);
    }

    private function setSetting(Request $request, string $key, string $value, string $type): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => 'ai',
                'updated_by_user_id' => $request->user()?->id,
            ]
        );
    }

    private function normaliseCsvSetting(string $value): string
    {
        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->implode(',');
    }

    private function ensureDevOwner(Request $request): void
    {
        if (strtolower((string) $request->user()?->email) !== 'jameskoen78@gmail.com') {
            abort(403);
        }
    }

    private function sourceSlugs(string $source): array
    {
        return collect(preg_split('/[\s,]+/', $source) ?: [])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function summarizeCollector(array $summary): array
    {
        return Arr::only($summary, [
            'sources',
            'parsed',
            'created',
            'duplicates',
            'skipped',
            'failed',
            'dry_run',
            'source_results',
        ]);
    }

    private function summarizeAiBatch(array $summary): array
    {
        return [
            'processed' => (int) ($summary['processed'] ?? 0),
            'created' => (int) ($summary['created'] ?? 0),
            'failed' => (int) ($summary['failed'] ?? 0),
            'skipped' => (int) ($summary['skipped'] ?? 0),
            'errors' => collect($summary['errors'] ?? [])
                ->map(fn (array $error): array => [
                    'id' => $error['brief_id'] ?? $error['article_id'] ?? $error['item_id'] ?? null,
                    'message' => (string) ($error['message'] ?? 'AI process failed.'),
                ])
                ->values()
                ->all(),
            'items' => collect($summary['articles'] ?? [])
                ->map(fn (Article $article): array => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                ])
                ->values()
                ->all(),
        ];
    }

    private function writerProcessStatus(): array
    {
        return [
            'active_sources' => ResearchSource::query()->active()->count(),
            'new_research_items' => ResearchItem::query()->where('status', ResearchItem::STATUS_NEW)->count(),
            'briefed_research_items' => ResearchItem::query()->where('status', ResearchItem::STATUS_BRIEFED)->count(),
            'pending_review_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_PENDING_REVIEW)->count(),
            'approved_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_APPROVED)->whereDoesntHave('article')->count(),
            'drafted_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_DRAFTED)->count(),
            'drafts_missing_images' => Article::query()
                ->whereNotNull('article_brief_id')
                ->whereNull('featured_image')
                ->whereIn('status', ['draft', 'pending_review', 'revision_requested'])
                ->count(),
        ];
    }

    private function writerProcessMessage(string $action, array $summaries): string
    {
        $parts = [];

        if (isset($summaries['collect'])) {
            $parts[] = "research {$summaries['collect']['created']} new";
        }

        if (isset($summaries['brief'])) {
            $parts[] = "briefs {$summaries['brief']['created']} created";
        }

        if (isset($summaries['write'])) {
            $parts[] = "drafts {$summaries['write']['created']} written";
        }

        if (isset($summaries['images'])) {
            $parts[] = "images {$summaries['images']['created']} generated";
        }

        return $parts === []
            ? ucfirst($action).' run completed.'
            : 'AI writer process completed: '.implode(', ', $parts).'.';
    }
}
