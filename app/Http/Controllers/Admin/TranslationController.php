<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Setting;
use App\Services\OpenRouterTranslationService;
use App\Services\PlatformTranslationBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    public function saveKey(Request $request, OpenRouterTranslationService $translator): JsonResponse
    {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:10', 'max:500'],
            'model' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'translation.openrouter_api_key'],
            [
                'value' => trim($validated['api_key']),
                'type' => 'secret',
                'group' => 'translations',
                'updated_by_user_id' => $request->user()->id,
            ]
        );

        if (filled($validated['model'] ?? null)) {
            Setting::updateOrCreate(
                ['key' => 'translation.openrouter_model'],
                [
                    'value' => trim($validated['model']),
                    'type' => 'string',
                    'group' => 'translations',
                    'updated_by_user_id' => $request->user()->id,
                ]
            );
        }

        return response()->json([
            'ok' => true,
            'message' => 'OpenRouter key saved.',
            'configured' => $translator->configured(),
            'source' => $translator->apiKeySource(),
            'masked_key' => $translator->maskedApiKey(),
            'model' => $translator->model(),
        ]);
    }

    public function preview(Request $request, OpenRouterTranslationService $translator): JsonResponse
    {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:12000'],
            'target_locale' => ['required', 'string', Rule::in(array_keys((array) config('localization.supported')))],
        ]);

        $translated = $translator->translateText($validated['text'], $validated['target_locale']);

        return response()->json([
            'ok' => $translated !== null,
            'message' => $translated === null ? 'Translation provider is not configured or returned no text.' : 'Translated.',
            'translated' => $translated,
            'model' => $translator->model(),
        ]);
    }

    public function translateArticle(Request $request, Article $article, OpenRouterTranslationService $translator): JsonResponse
    {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'target_locale' => ['required', 'string', Rule::in(array_keys((array) config('localization.supported')))],
            'force' => ['nullable', 'boolean'],
        ]);

        $result = $translator->translateModel($article, $validated['target_locale'], $request->boolean('force'));

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'Translation request completed.',
            'model' => $translator->model(),
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function batch(Request $request, PlatformTranslationBatchService $batch): JsonResponse
    {
        $this->ensureDevOwner($request);

        $sectionKeys = collect($batch->sectionOptions())->pluck('key')->all();

        $validated = $request->validate([
            'section' => ['required', 'string', Rule::in([...$sectionKeys, 'all'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'force' => ['nullable', 'boolean'],
        ]);

        $result = $batch->translate(
            $validated['section'],
            (int) ($validated['limit'] ?? 20),
            $request->boolean('force')
        );

        $message = "Processed {$result['processed']} translation targets: {$result['translated']} translated, {$result['skipped']} current, {$result['failed']} failed.";

        if (! empty($result['errors'])) {
            $message .= ' First issue: '.collect($result['errors'])->first();
        }

        return response()->json([
            ...$result,
            'message' => $message,
            'status' => $batch->status(),
        ], $result['ok'] ? 200 : 422);
    }

    private function ensureDevOwner(Request $request): void
    {
        if (strtolower((string) $request->user()?->email) !== 'jameskoen78@gmail.com') {
            abort(403);
        }
    }
}
