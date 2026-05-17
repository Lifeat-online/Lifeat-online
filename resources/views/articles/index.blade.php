@extends('layouts.public')

@section('title', ($pageTitle ?? 'Articles').' | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>{{ $pageTitle ?? 'Articles' }}</h2>
                <p class="muted">{{ $pageDescription ?? 'Browse published articles, filter by category, tag, or location, and follow local coverage across the Eastern Freestate.' }}</p>
            </div>
        </div>

        @if (! empty($activeCategory))
            <div class="card" style="margin-bottom: 1rem;">
                <p class="muted" style="margin: 0;">Viewing archive for <strong>{{ $activeCategory->name }}</strong>. <a href="{{ route('articles.index') }}">View all articles</a>.</p>
            </div>
        @elseif (! empty($activeTag))
            <div class="card" style="margin-bottom: 1rem;">
                <p class="muted" style="margin: 0;">Viewing articles tagged <strong>{{ $activeTag->name }}</strong>. <a href="{{ route('articles.index') }}">View all articles</a>.</p>
            </div>
        @elseif (! empty($activeLocation))
            <div class="card" style="margin-bottom: 1rem;">
                <p class="muted" style="margin: 0;">Viewing articles linked to <strong>{{ $activeLocation->name }}</strong>. <a href="{{ route('articles.index') }}">View all articles</a>.</p>
            </div>
        @elseif (! empty($activeAuthor))
            <div class="card" style="margin-bottom: 1rem;">
                <p class="muted" style="margin: 0;">Viewing articles by <strong>{{ $activeAuthor->name }}</strong>. <a href="{{ route('articles.index') }}">View all articles</a>.</p>
            </div>
        @endif

    <section class="section">
        <div class="detail-grid">
            <div class="stack">
                @forelse ($articles as $article)
                    <article class="card">
                        <div class="meta">
                            <span>{{ optional($article->published_at)->format('j M Y') ?: 'Draft' }}</span>
                            @if ($article->author)
                                <span>
                                    @if ($article->author->username)
                                        <a href="{{ route('articles.authors.show', $article->author) }}">{{ $article->author->name }}</a>
                                    @else
                                        {{ $article->author->name }}
                                    @endif
                                </span>
                            @endif
                        </div>
                        <h3><a href="{{ route('articles.show', $article) }}">{{ $article->localizedTitle() }}</a></h3>
                        <p>{{ $article->localizedExcerpt() ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->localizedBody()), 180) }}</p>
                        <div>
                            @foreach ($article->categories as $category)
                                <a href="{{ route('articles.categories.show', $category) }}" class="badge">{{ $category->name }}</a>
                            @endforeach
                            @foreach ($article->tags as $tag)
                                <a href="{{ route('articles.tags.show', $tag) }}" class="badge">{{ $tag->name }}</a>
                            @endforeach
                            @foreach ($article->locations as $location)
                                <a href="{{ route('articles.locations.show', $location) }}" class="badge">{{ $location->name }}</a>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="empty-state">No articles match your current filters.</div>
                @endforelse

                <div style="margin-top: 1rem;">{{ $articles->links() }}</div>
            </div>

            <aside class="stack">
                <form method="get" class="card">
                    <h3 style="margin-bottom: 1rem;">Filter Articles</h3>
                    <div class="form-stack">
                        <div>
                            <label for="q">Search</label>
                            <input id="q" name="q" value="{{ $filters['q'] }}" placeholder="Keyword...">
                        </div>
                        <div style="margin-top: 1rem;">
                            <label for="category">Category</label>
                            <select id="category" name="category" style="width: 100%;">
                                <option value="">All categories</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="margin-top: 1rem;">
                            <label for="tag">Tag</label>
                            <select id="tag" name="tag" style="width: 100%;">
                                <option value="">All tags</option>
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag->slug }}" @selected($filters['tag'] === $tag->slug)>{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="margin-top: 1rem;">
                            <label for="location">Location</label>
                            <select id="location" name="location" style="width: 100%;">
                                <option value="">All locations</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->slug }}" @selected($filters['location'] === $location->slug)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="margin-top: 1.25rem;">
                            <button class="button" type="submit" style="width: 100%;">Filter</button>
                            <a href="{{ route('articles.index') }}" class="button-link" style="display: block; text-align: center; margin-top: 0.5rem;">Reset</a>
                        </div>
                    </div>
                </form>

                @foreach ($sidebarAdCampaigns as $ad)
                    <x-ad-campaign-card :campaign="$ad" />
                @endforeach
            </aside>
        </div>
    </section>
@endsection
