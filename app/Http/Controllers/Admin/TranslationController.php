<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\OpenRouterTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
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
        ], $translated === null ? 422 : 200);
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

    private function ensureDevOwner(Request $request): void
    {
        if (strtolower((string) $request->user()?->email) !== 'jameskoen78@gmail.com') {
            abort(403);
        }
    }
}
