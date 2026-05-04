@extends('layouts.public')

@section('title', $article->title.' | Articles')

@section('content')
    <section class="section detail-grid">
        <div class="stack">
            <article class="card">
                <div class="meta">
                    <span>{{ optional($article->published_at)->format('j M Y') ?: 'Draft' }}</span>
                    <span>
                        @if ($article->author && $article->author->username)
                            <a href="{{ route('articles.authors.show', $article->author) }}">{{ $article->author->name }}</a>
                        @else
                            {{ $article->author?->name ?: 'Editorial team' }}
                        @endif
                    </span>
                </div>
                @if ($article->featured_image)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($article->featured_image) }}" alt="" style="width:100%; height:280px; object-fit:cover; border-radius:12px; margin:0 0 1rem;">
                @endif
                <h2>{{ $article->title }}</h2>
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
                <div>{!! nl2br(e($article->body ?: $article->excerpt ?: 'Article body coming soon.')) !!}</div>
            </article>
        </div>

        <aside class="stack">
            <article class="card">
                <h3>Publishing Details</h3>
                <p class="muted">Status: {{ ucfirst($article->status) }}</p>
                <p class="muted">Published: {{ optional($article->published_at)->format('j M Y') ?: 'Not published yet' }}</p>
                <p style="margin-top: 1rem;"><a href="{{ route('articles.index') }}">Back to all articles</a></p>
            </article>

            <article class="card">
                <h3>Related Articles</h3>
                @forelse ($relatedArticles as $relatedArticle)
                    <p><a href="{{ route('articles.show', $relatedArticle) }}">{{ $relatedArticle->title }}</a></p>
                @empty
                    <p class="muted">No related articles found yet.</p>
                @endforelse
            </article>

            @foreach ($sidebarAdCampaigns as $ad)
                <x-ad-campaign-card :campaign="$ad" />
            @endforeach
        </aside>
    </section>
@endsection
