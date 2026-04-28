<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\LocationNode;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        return $this->renderArchive($request);
    }

    public function category(Request $request, Category $category): View
    {
        abort_if($category->type !== 'article', 404);

        return $this->renderArchive($request, $category);
    }

    public function author(Request $request, User $user): View
    {
        return $this->renderArchive($request, null, $user);
    }

    public function tag(Request $request, Tag $tag): View
    {
        abort_if($tag->type !== 'article', 404);

        return $this->renderArchive($request, null, null, $tag);
    }

    public function location(Request $request, LocationNode $locationNode): View
    {
        return $this->renderArchive($request, null, null, null, $locationNode);
    }

    public function show(Article $article): View
    {
        abort_if($article->status !== 'published', 404);

        $article->load(['author', 'categories', 'tags', 'locations']);

        $categoryIds = $article->categories->modelKeys();

        $relatedArticles = Article::with(['author', 'categories', 'tags', 'locations'])
            ->published()
            ->whereKeyNot($article->getKey())
            ->when(! empty($categoryIds), function ($query) use ($categoryIds) {
                $query->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds));
            })
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('articles.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
        ]);
    }

    private function renderArchive(
        Request $request,
        ?Category $forcedCategory = null,
        ?User $forcedAuthor = null,
        ?Tag $forcedTag = null,
        ?LocationNode $forcedLocation = null
    ): View
    {
        $search = trim((string) $request->string('q'));
        $categorySlug = $forcedCategory?->slug ?? trim((string) $request->string('category'));
        $tagSlug = $forcedTag?->slug ?? trim((string) $request->string('tag'));
        $locationSlug = $forcedLocation?->slug ?? trim((string) $request->string('location'));

        $articles = Article::with(['author', 'categories', 'tags', 'locations'])
            ->published()
            ->when($forcedAuthor, function ($query) use ($forcedAuthor) {
                $query->where('user_id', $forcedAuthor->id);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->when($categorySlug !== '', function ($query) use ($categorySlug) {
                $query->whereHas('categories', fn ($categories) => $categories->where('slug', $categorySlug));
            })
            ->when($tagSlug !== '', function ($query) use ($tagSlug) {
                $query->whereHas('tags', fn ($tags) => $tags->where('slug', $tagSlug));
            })
            ->when($locationSlug !== '', function ($query) use ($locationSlug) {
                $query->whereHas('locations', fn ($locations) => $locations->where('slug', $locationSlug));
            })
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        $activeCategory = $forcedCategory;
        $activeTag = $forcedTag;
        $activeLocation = $forcedLocation;

        if (! $activeCategory && $categorySlug !== '') {
            $activeCategory = Category::query()
                ->where('type', 'article')
                ->where('slug', $categorySlug)
                ->first();
        }

        if (! $activeTag && $tagSlug !== '') {
            $activeTag = Tag::query()
                ->where('type', 'article')
                ->where('slug', $tagSlug)
                ->first();
        }

        if (! $activeLocation && $locationSlug !== '') {
            $activeLocation = LocationNode::query()
                ->where('slug', $locationSlug)
                ->first();
        }

        return view('articles.index', [
            'articles' => $articles,
            'categories' => Category::query()
                ->where('type', 'article')
                ->orderBy('name')
                ->get(),
            'tags' => Tag::query()
                ->where('type', 'article')
                ->orderBy('name')
                ->get(),
            'locations' => LocationNode::query()
                ->orderBy('name')
                ->get(),
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
                'tag' => $tagSlug,
                'location' => $locationSlug,
            ],
            'activeCategory' => $activeCategory,
            'activeAuthor' => $forcedAuthor,
            'activeTag' => $activeTag,
            'activeLocation' => $activeLocation,
            'pageTitle' => $forcedAuthor
                ? 'Articles by '.$forcedAuthor->name
                : ($activeCategory
                    ? $activeCategory->name.' Articles'
                    : ($activeTag
                        ? $activeTag->name.' Articles'
                        : ($activeLocation ? $activeLocation->name.' Articles' : 'Articles'))),
            'pageDescription' => $forcedAuthor
                ? ($forcedAuthor->bio ?: 'Browse published articles by '.$forcedAuthor->name.'.')
                : ($activeCategory
                    ? ($activeCategory->description ?: 'Browse published articles in '.$activeCategory->name.'.')
                    : ($activeTag
                        ? ($activeTag->description ?: 'Browse published articles tagged '.$activeTag->name.'.')
                        : ($activeLocation
                            ? 'Browse published articles linked to '.$activeLocation->name.'.'
                            : 'Editorial content pages replacing the WordPress post archive and single post views.'))),
        ]);
    }
}
