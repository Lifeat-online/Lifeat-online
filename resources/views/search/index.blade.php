@extends('layouts.public')

@section('title', 'Search | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Search</h2>
                <p class="muted">Search across articles, businesses, events, and classifieds from one discovery page.</p>
            </div>
        </div>
        <form method="get" class="card form-grid">
            <div>
                <label for="q">Query</label>
                <input id="q" name="q" value="{{ $filters['q'] }}" placeholder="Keywords">
            </div>
            <div>
                <label for="loc">Location</label>
                <input id="loc" name="loc" value="{{ $filters['loc'] }}" placeholder="City or region">
            </div>
            <div>
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category['slug'] }}" @selected($filters['category'] === $category['slug'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button class="button" type="submit">Search</button>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Businesses</h3>
                <p class="muted">{{ $listings->total() }} result{{ $listings->total() === 1 ? '' : 's' }}</p>
            </div>
        </div>
        @forelse ($listings as $listing)
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card">
                <h4><a href="{{ route('directory.show', $listing) }}">{{ $listing->title }}</a></h4>
                <p class="muted">{{ $listing->city ?: 'Location pending' }}</p>
                @if ($listing->categories->isNotEmpty())
                    <div>
                        @foreach ($listing->categories as $category)
                            <span class="badge">{{ $category->name }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No businesses found.</div>
        @endforelse

        @if ($listings->hasPages())
            <div style="margin-top: 1rem;">{{ $listings->links() }}</div>
        @endif
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Events</h3>
                <p class="muted">{{ $events->total() }} result{{ $events->total() === 1 ? '' : 's' }}</p>
            </div>
        </div>
        @forelse ($events as $event)
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card">
                <h4><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></h4>
                <p class="muted">{{ optional($event->start_at)->format('j M Y g:i A') }}</p>
                @if ($event->city)
                    <p>{{ $event->city }}</p>
                @endif
                @if ($event->categories->isNotEmpty())
                    <div>
                        @foreach ($event->categories as $category)
                            <span class="badge">{{ $category->name }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No events found.</div>
        @endforelse

        @if ($events->hasPages())
            <div style="margin-top: 1rem;">{{ $events->links() }}</div>
        @endif
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Articles</h3>
                <p class="muted">{{ $articles->total() }} result{{ $articles->total() === 1 ? '' : 's' }}</p>
            </div>
        </div>
        @forelse ($articles as $article)
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card">
                <h4><a href="{{ route('articles.show', $article) }}">{{ $article->title }}</a></h4>
                <p class="muted">{{ optional($article->published_at)->format('j M Y') }}</p>
                @if ($article->author)
                    <p>{{ $article->author->name }}</p>
                @endif
                @if ($article->categories->isNotEmpty())
                    <div>
                        @foreach ($article->categories as $category)
                            <span class="badge">{{ $category->name }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No articles found.</div>
        @endforelse

        @if ($articles->hasPages())
            <div style="margin-top: 1rem;">{{ $articles->links() }}</div>
        @endif
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h3>Classifieds</h3>
                <p class="muted">{{ $classifieds->total() }} result{{ $classifieds->total() === 1 ? '' : 's' }}</p>
            </div>
        </div>
        @forelse ($classifieds as $classified)
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card">
                <h4><a href="{{ route('classifieds.show', $classified) }}">{{ $classified->title }}</a></h4>
                <p class="muted">{{ $classified->city ?: 'Location pending' }}</p>
                <p>
                    @if ($classified->contact_for_price)
                        Contact for price
                    @elseif (! is_null($classified->price))
                        {{ $classified->currency }} {{ number_format($classified->price, 2) }}
                    @else
                        -
                    @endif
                </p>
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No classifieds found.</div>
        @endforelse

        @if ($classifieds->hasPages())
            <div style="margin-top: 1rem;">{{ $classifieds->links() }}</div>
        @endif
    </section>

    @if ($listings->total() === 0 && $events->total() === 0 && $articles->total() === 0 && $classifieds->total() === 0)
        <section class="section">
            <div class="card">
                <h3>Need more visibility?</h3>
                <p class="muted">No matching results were found. You can browse the directory or explore promotion options.</p>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
                    <a class="button" href="{{ route('directory.index') }}">Browse businesses</a>
                    <a class="button" href="{{ route('advertise.index') }}">Advertise with us</a>
                </div>
            </div>
        </section>
    @endif
@endsection
