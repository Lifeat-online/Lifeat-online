<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Support\Caching\PublicReadCache;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q'));
        $location = trim((string) $request->string('loc'));
        $category = trim((string) $request->string('category'));
        $terms = $this->searchTerms($q);
        $locationTerms = $this->searchTerms($location);

        $listingsQuery = Listing::with('categories')->published();
        $this->applyKeywordSearch($listingsQuery, [
            'title',
            'excerpt',
            'description',
            'city',
            'region',
            'country',
        ], $terms, [
            'categories' => ['name', 'slug', 'description'],
        ]);
        $this->applyKeywordSearch($listingsQuery, ['city', 'region', 'country'], $locationTerms);
        $this->applyRelevanceOrdering($listingsQuery, [
            'title' => 60,
            'excerpt' => 30,
            'city' => 25,
            'region' => 15,
            'description' => 12,
            'country' => 8,
        ], $terms, [
            'categories as search_category_matches_count' => ['name', 'slug', 'description'],
        ]);

        $listings = $listingsQuery
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($cats) => $cats->where('slug', $category));
            })
            ->orderByDesc('is_featured')
            ->orderBy('title')
            ->paginate(10, ['*'], 'listings_page')
            ->withQueryString();

        $eventsQuery = Event::with(['categories', 'listing'])->published();
        $this->applyKeywordSearch($eventsQuery, [
            'title',
            'excerpt',
            'description',
            'venue_name',
            'city',
            'region',
            'country',
        ], $terms, [
            'categories' => ['name', 'slug', 'description'],
            'listing' => ['title', 'excerpt', 'description', 'city', 'region'],
        ]);
        $this->applyKeywordSearch($eventsQuery, ['city', 'region', 'country'], $locationTerms);
        $this->applyRelevanceOrdering($eventsQuery, [
            'title' => 60,
            'venue_name' => 35,
            'excerpt' => 30,
            'city' => 25,
            'region' => 15,
            'description' => 12,
            'country' => 8,
        ], $terms, [
            'categories as search_category_matches_count' => ['name', 'slug', 'description'],
            'listing as search_listing_matches_count' => ['title', 'excerpt', 'description', 'city', 'region'],
        ]);

        $events = $eventsQuery
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($cats) => $cats->where('slug', $category));
            })
            ->orderBy('start_at')
            ->paginate(10, ['*'], 'events_page')
            ->withQueryString();

        $articlesQuery = Article::with(['author', 'categories'])->published();
        $this->applyKeywordSearch($articlesQuery, ['title', 'excerpt', 'body'], $terms, [
            'categories' => ['name', 'slug', 'description'],
            'tags' => ['name', 'slug', 'description'],
            'locations' => ['name', 'slug', 'type'],
        ]);
        $this->applyKeywordSearch($articlesQuery, [], $locationTerms, [
            'locations' => ['name', 'slug', 'type'],
        ]);
        $this->applyRelevanceOrdering($articlesQuery, [
            'title' => 60,
            'excerpt' => 30,
            'body' => 10,
        ], $terms, [
            'categories as search_category_matches_count' => ['name', 'slug', 'description'],
            'tags as search_tag_matches_count' => ['name', 'slug', 'description'],
            'locations as search_location_matches_count' => ['name', 'slug', 'type'],
        ]);

        $articles = $articlesQuery
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('categories', fn ($cats) => $cats->where('slug', $category));
            })
            ->latest('published_at')
            ->paginate(10, ['*'], 'articles_page')
            ->withQueryString();

        $classifiedsQuery = Classified::query()->where('status', Classified::STATUS_PUBLISHED);
        $this->applyKeywordSearch($classifiedsQuery, [
            'title',
            'description',
            'city',
            'region',
            'country',
        ], $terms);
        $this->applyKeywordSearch($classifiedsQuery, ['city', 'region', 'country'], $locationTerms);
        $this->applyRelevanceOrdering($classifiedsQuery, [
            'title' => 60,
            'city' => 25,
            'region' => 15,
            'description' => 12,
            'country' => 8,
        ], $terms);

        $classifieds = $classifiedsQuery
            ->when($category !== '', function ($query) {
                // Classified category filtering is not implemented yet, so avoid mixing
                // unfiltered classifieds into a category-specific result set.
                $query->whereRaw('1 = 0');
            })
            ->latest('published_at')
            ->paginate(10, ['*'], 'classifieds_page')
            ->withQueryString();

        return view('search.index', [
            'filters' => [
                'q' => $q,
                'loc' => $location,
                'category' => $category,
            ],
            'categories' => PublicReadCache::searchCategories(),
            'listings' => $listings,
            'events' => $events,
            'articles' => $articles,
            'classifieds' => $classifieds,
        ]);
    }

    private function searchTerms(string $value): array
    {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($value, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($terms)), 0, 8);
    }

    private function applyKeywordSearch(Builder $query, array $columns, array $terms, array $relations = []): Builder
    {
        foreach ($terms as $term) {
            $query->where(function (Builder $inner) use ($columns, $relations, $term) {
                $this->whereTermMatches($inner, $columns, $term, $relations);
            });
        }

        return $query;
    }

    private function applyRelevanceOrdering(Builder $query, array $weightedColumns, array $terms, array $relationCounts = []): Builder
    {
        if ($terms === []) {
            return $query;
        }

        foreach ($relationCounts as $relationExpression => $columns) {
            $query->withCount([
                $relationExpression => function (Builder $related) use ($columns, $terms) {
                    $related->where(function (Builder $inner) use ($columns, $terms) {
                        $hasClause = false;

                        foreach ($terms as $term) {
                            foreach ($columns as $column) {
                                $this->addLikeClause($inner, $column, $term, $hasClause);
                                $hasClause = true;
                            }
                        }
                    });
                },
            ]);
        }

        $scoreParts = ['0'];
        $bindings = [];

        foreach ($terms as $term) {
            foreach ($weightedColumns as $column => $weight) {
                $column = $this->searchColumn($column);
                $scoreParts[] = "CASE WHEN LOWER(COALESCE({$column}, '')) = ? THEN ".($weight * 4).' ELSE 0 END';
                $bindings[] = $term;
                $scoreParts[] = "CASE WHEN LOWER(COALESCE({$column}, '')) LIKE ? THEN ".($weight * 2).' ELSE 0 END';
                $bindings[] = $term.'%';
                $scoreParts[] = "CASE WHEN LOWER(COALESCE({$column}, '')) LIKE ? THEN {$weight} ELSE 0 END";
                $bindings[] = '%'.$term.'%';
            }
        }

        $query->orderByRaw('('.implode(' + ', $scoreParts).') DESC', $bindings);

        foreach (array_keys($relationCounts) as $relationExpression) {
            $query->orderByDesc($this->relationCountAlias($relationExpression));
        }

        return $query;
    }

    private function whereTermMatches(Builder $query, array $columns, string $term, array $relations = []): void
    {
        $hasClause = false;

        foreach ($columns as $column) {
            $this->addLikeClause($query, $column, $term, $hasClause);
            $hasClause = true;
        }

        foreach ($relations as $relation => $relationColumns) {
            $method = $hasClause ? 'orWhereHas' : 'whereHas';
            $query->{$method}($relation, function (Builder $related) use ($relationColumns, $term) {
                $related->where(function (Builder $inner) use ($relationColumns, $term) {
                    $hasRelationClause = false;

                    foreach ($relationColumns as $column) {
                        $this->addLikeClause($inner, $column, $term, $hasRelationClause);
                        $hasRelationClause = true;
                    }
                });
            });
            $hasClause = true;
        }

        if (! $hasClause) {
            $query->whereRaw('1 = 0');
        }
    }

    private function addLikeClause(Builder $query, string $column, string $term, bool $or): void
    {
        $sql = 'LOWER(COALESCE('.$this->searchColumn($column).", '')) LIKE ?";
        $bindings = ['%'.$term.'%'];

        if ($or) {
            $query->orWhereRaw($sql, $bindings);

            return;
        }

        $query->whereRaw($sql, $bindings);
    }

    private function searchColumn(string $column): string
    {
        if (! preg_match('/^[A-Za-z0-9_.]+$/', $column)) {
            throw new \InvalidArgumentException('Unsupported search column.');
        }

        return $column;
    }

    private function relationCountAlias(string $relationExpression): string
    {
        if (preg_match('/\bas\s+([A-Za-z0-9_]+)$/i', $relationExpression, $matches)) {
            return $matches[1];
        }

        return $relationExpression.'_count';
    }
}
