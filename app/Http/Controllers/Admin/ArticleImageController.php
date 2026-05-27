<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\AiImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ArticleImageController extends Controller
{
    public function store(Request $request, Article $article, AiImageService $images): JsonResponse|RedirectResponse
    {
        try {
            $result = $images->generateForArticle($article, $request->user(), $request->boolean('force'));
        } catch (Throwable $exception) {
            Log::error('Image Agent request failed unexpectedly.', [
                'article_id' => $article->id,
                'message' => $exception->getMessage(),
            ]);

            $result = [
                'ok' => false,
                'message' => 'Image Agent server error: '.Str::limit($exception->getMessage(), 300, ''),
            ];
        }

        if ($request->expectsJson()) {
            return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
        }

        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('admin.articles.edit', $article)
                ->withErrors(['image_agent' => $result['message'] ?? 'Image Agent could not create an illustration.']);
        }

        return redirect()
            ->route('admin.articles.edit', $article)
            ->with('status', $result['message'] ?? 'Image Agent illustration generated.');
    }
}
