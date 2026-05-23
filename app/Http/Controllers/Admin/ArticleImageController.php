<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\AiImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArticleImageController extends Controller
{
    public function store(Request $request, Article $article, AiImageService $images): JsonResponse|RedirectResponse
    {
        $result = $images->generateForArticle($article, $request->user(), $request->boolean('force'));

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
