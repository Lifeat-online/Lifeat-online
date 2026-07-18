<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Article;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;

class ContentSearchTool implements OperatorTool
{
    public function name(): string
    {
        return 'content.search';
    }

    public function risk(): string
    {
        return 'R0';
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:all,listing,article'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return 'read-only';
    }

    public function execute(User $user, array $arguments): array
    {
        $query = Str::limit($arguments['query'], 255, '');
        $type = $arguments['type'] ?? 'all';
        $limit = (int) ($arguments['limit'] ?? 10);
        $listings = in_array($type, ['all', 'listing'], true)
            ? Listing::query()->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('city', 'like', "%{$query}%"))
                ->limit($limit)->get(['id', 'title', 'slug', 'city', 'status', 'website_url'])->map(fn (Listing $listing): array => ['type' => 'listing', ...$listing->toArray()])
            : collect();
        $articles = in_array($type, ['all', 'article'], true)
            ? Article::query()->where(fn ($builder) => $builder->where('title', 'like', "%{$query}%")->orWhere('excerpt', 'like', "%{$query}%"))
                ->limit($limit)->get(['id', 'title', 'slug', 'status', 'published_at'])->map(fn (Article $article): array => ['type' => 'article', ...$article->toArray()])
            : collect();

        return ['query' => $query, 'results' => $listings->concat($articles)->take($limit)->values()->all()];
    }
}
