@extends('layouts.public')

@section('title', 'Eastern Freestate | Home')

@push('styles')
    <style>
        .home-wrap {
            display: grid;
            gap: 2rem;
        }
        .home-hero {
            display: grid;
            gap: 1.25rem;
            grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.85fr);
            align-items: start;
        }
        .hero-panel {
            position: relative;
            overflow: hidden;
            padding: 2.25rem;
            border-radius: 26px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background:
                radial-gradient(900px circle at 18% 18%, rgba(167, 243, 208, 0.28), transparent 40%),
                radial-gradient(800px circle at 86% 18%, rgba(147, 197, 253, 0.32), transparent 38%),
                radial-gradient(900px circle at 60% 105%, rgba(216, 180, 254, 0.24), transparent 45%),
                linear-gradient(135deg, #0b1220, #1d4ed8 58%, #1e3a8a);
            color: #eff6ff;
            box-shadow: 0 26px 60px rgba(2, 6, 23, 0.28);
        }
        html[data-theme="dark"] .hero-panel {
            background:
                radial-gradient(900px circle at 18% 18%, rgba(96, 165, 250, 0.16), transparent 40%),
                radial-gradient(900px circle at 82% 22%, rgba(168, 85, 247, 0.14), transparent 40%),
                linear-gradient(135deg, #020617, #0b1220 55%, #1e3a8a);
        }
        .hero-panel::after {
            content: "";
            position: absolute;
            inset: -1px;
            pointer-events: none;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03), rgba(255,255,255,0.10));
            opacity: 0.55;
            mask: linear-gradient(#000, #000) content-box, linear-gradient(#000, #000);
            -webkit-mask: linear-gradient(#000, #000) content-box, linear-gradient(#000, #000);
            padding: 1px;
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }
        .hero-panel .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.16);
            font-size: 0.86rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .hero-heading {
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.05;
            margin: 1rem 0 0.85rem;
            letter-spacing: -0.02em;
        }
        .hero-copy {
            max-width: 46rem;
            color: rgba(239, 246, 255, 0.86);
            font-size: 1.05rem;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .hero-primary,
        .hero-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.9rem 1.2rem;
            font-weight: 700;
            text-decoration: none;
            position: relative;
            z-index: 1;
        }
        .hero-primary {
            background: #ffffff;
            color: #0f172a;
        }
        .hero-secondary {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }
        .hero-primary:hover,
        .hero-secondary:hover {
            transform: translateY(-1px);
            transition: transform 150ms ease;
            text-decoration: none;
        }
        .search-panel {
            border-radius: 22px;
            padding: 1.4rem;
            position: sticky;
            top: 1.25rem;
        }
        .search-stack > * + * {
            margin-top: 1rem;
        }
        .search-form {
            display: grid;
            gap: 0.9rem;
        }
        .search-form button {
            width: 100%;
        }
        .stat-grid-home {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(3, 1fr);
            margin-top: 1.6rem;
        }
        .stat-tile {
            border-radius: 18px;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.16);
            display: grid;
            gap: 0.35rem;
        }
        .stat-tile strong {
            display: block;
            font-size: 1.7rem;
            line-height: 1;
            margin-bottom: 0.1rem;
        }
        .stat-tile span {
            color: rgba(239, 246, 255, 0.9);
            font-size: 0.92rem;
        }
        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem 1rem;
            margin-top: 1.25rem;
            color: rgba(239, 246, 255, 0.78);
            font-size: 0.92rem;
        }
        .hero-meta a {
            color: rgba(255, 255, 255, 0.92);
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .lead-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.95fr);
        }
        .feature-media {
            display: block;
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 18px;
            margin-bottom: 1rem;
            background: #dbeafe;
        }
        .media-fallback {
            height: 320px;
            border-radius: 18px;
            margin-bottom: 1rem;
            border: 1px dashed var(--border);
            background:
                radial-gradient(600px circle at 20% 20%, rgba(29, 78, 216, 0.10), transparent 42%),
                radial-gradient(600px circle at 80% 30%, rgba(99, 102, 241, 0.10), transparent 40%),
                var(--surface);
        }
        .story-list {
            display: grid;
            gap: 0.85rem;
        }
        .story-item {
            display: grid;
            gap: 0.5rem;
            border-radius: 18px;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .mini-meta,
        .eyebrow {
            color: var(--muted);
            font-size: 0.88rem;
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }
        .category-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
        .category-card {
            display: block;
            border-radius: 18px;
            padding: 1.15rem;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
            transition: transform 150ms ease, box-shadow 150ms ease, border-color 150ms ease;
        }
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            border-color: rgba(29, 78, 216, 0.25);
            text-decoration: none;
        }
        .listing-card-home,
        .event-card-home {
            overflow: hidden;
        }
        .listing-logo {
            width: 68px;
            height: 68px;
            object-fit: contain;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #fff;
            padding: 0.45rem;
            margin-bottom: 1rem;
        }
        .promo-strip,
        .community-strip {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: stretch;
        }
        .promo-card {
            padding: 1.6rem;
            border-radius: 24px;
            border: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(29, 78, 216, 0.08), rgba(30, 64, 175, 0.02)), var(--surface);
        }
        html[data-theme="dark"] .promo-card {
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.08), rgba(15, 23, 42, 0.8)), var(--surface);
        }
        .cta-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .cta-card {
            padding: 1.25rem;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: var(--surface);
            transition: transform 150ms ease, box-shadow 150ms ease;
        }
        .cta-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }
        .section-subtitle {
            max-width: 42rem;
            margin: 0.25rem 0 0;
            color: var(--muted);
        }
        .section-head a {
            font-weight: 700;
        }
        .button,
        .button-link {
            transition: transform 150ms ease, box-shadow 150ms ease, background 150ms ease;
        }
        .button:hover,
        .button-link:hover {
            transform: translateY(-1px);
            text-decoration: none;
        }
        .home-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(148,163,184,0.38), transparent);
            margin: 0.25rem 0;
        }
        .railway-admin-seed-wrap {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: grid;
            gap: 0.35rem;
            justify-items: end;
            z-index: 5;
        }
        .railway-admin-seed-btn {
            appearance: none;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(15, 23, 42, 0.42);
            color: rgba(239, 246, 255, 0.92);
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }
        .railway-admin-seed-btn[disabled] {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .railway-admin-seed-status {
            font-size: 0.82rem;
            color: rgba(239, 246, 255, 0.82);
            max-width: 18rem;
            text-align: right;
        }
        @media (max-width: 960px) {
            .home-hero,
            .lead-grid,
            .promo-strip,
            .community-strip {
                grid-template-columns: 1fr;
            }
            .stat-grid-home {
                grid-template-columns: 1fr;
            }
            .search-panel {
                position: static;
            }
        }
    </style>
@endpush

@section('content')
    <div class="home-wrap">
    <section class="home-hero">
        <div class="hero-panel">
            @if (!empty($railwayAdminBootstrapVisible))
                <div class="railway-admin-seed-wrap">
                    <button type="button" class="railway-admin-seed-btn" data-railway-admin-seed>Dev admin login</button>
                    <div class="railway-admin-seed-status" data-railway-admin-seed-status aria-live="polite"></div>
                </div>
            @endif
            <span class="hero-kicker">Eastern Freestate local guide</span>
            <h1 class="hero-heading">Local news, trusted businesses, community events, and space to promote what matters.</h1>
            <p class="hero-copy">
                Life@ News is your digital guide to the Eastern Freestate — read local stories, discover businesses near you, find upcoming events, and connect with your community.
            </p>
            <div class="hero-actions">
                <a href="{{ route('directory.index') }}" class="hero-primary">Browse Directory</a>
                <a href="{{ route('events.index') }}" class="hero-secondary">See Upcoming Events</a>
                <a href="{{ route('advertise.index') }}" class="hero-secondary">Advertise With Us</a>
            </div>
            <div class="stat-grid-home">
                <div class="stat-tile">
                    <strong>{{ $articleCount }}</strong>
                    <span>Published articles</span>
                </div>
                <div class="stat-tile">
                    <strong>{{ $listingCount }}</strong>
                    <span>Visible businesses</span>
                </div>
                <div class="stat-tile">
                    <strong>{{ $eventCount }}</strong>
                    <span>Upcoming events</span>
                </div>
            </div>
            <div class="hero-meta" aria-label="Helpful links">
                <span><a href="{{ route('articles.index') }}">Read the latest stories</a></span>
                <span>·</span>
                <span><a href="{{ route('add-listing.index') }}">Add your listing</a></span>
                <span>·</span>
                <span><a href="{{ route('contact.index') }}">Contact the team</a></span>
            </div>
        </div>

        <div class="card search-panel search-stack">
            <div>
                <span class="eyebrow">Search the region</span>
                <h2 class="h2-tight">Find businesses, events, and stories quickly.</h2>
                <p class="section-subtitle">Use the same search surface to jump into directory results, editorial content, or local event discovery.</p>
            </div>
            <form method="get" action="{{ route('search.index') }}" class="search-form">
                <div>
                    <label for="home-q">What are you looking for?</label>
                    <input id="home-q" name="q" placeholder="Search businesses, events, or articles">
                </div>
                <div>
                    <label for="home-loc">Location</label>
                    <input id="home-loc" name="loc" placeholder="Bethlehem, Clarens, Fouriesburg...">
                </div>
                <button class="button" type="submit">Search Eastern Freestate</button>
            </form>
            <div class="home-divider" role="separator" aria-hidden="true"></div>
            <div class="card pad-10">
                <span class="eyebrow">Quick links</span>
                <p class="lh-17 mt-05 mb-0">
                    <a href="{{ route('directory.index') }}">Business directory</a>
                    · <a href="{{ route('events.index') }}">Events</a>
                    · <a href="{{ route('articles.index') }}">Articles</a>
                    · <a href="{{ route('classifieds.index') }}">Classifieds</a>
                </p>
                <p class="muted mt-065 mb-0">Tip: use a keyword + town name for the fastest results.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h2>Top Story and Latest News</h2>
                <p class="section-subtitle">Catch up on the latest local coverage, features, and community stories.</p>
            </div>
            <a href="{{ route('articles.index') }}">View all articles</a>
        </div>

        @if ($leadArticle)
            <div class="lead-grid">
                <article class="card">
                    @if ($leadArticle->featured_image)
                        <img class="feature-media" src="{{ \Illuminate\Support\Facades\Storage::url($leadArticle->featured_image) }}" alt="{{ $leadArticle->title }}">
                    @else
                        <div class="media-fallback" aria-hidden="true"></div>
                    @endif
                    <span class="eyebrow">Lead story</span>
                    <h3 class="lead-title">
                        <a href="{{ route('articles.show', $leadArticle) }}">{{ $leadArticle->title }}</a>
                    </h3>
                    <div class="mini-meta">
                        {{ optional($leadArticle->published_at)->format('j M Y') ?: 'Draft' }}
                        @if ($leadArticle->author)
                            · {{ $leadArticle->author->name }}
                        @endif
                    </div>
                    <p class="mt-08">{{ $leadArticle->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $leadArticle->body), 240) }}</p>
                    <div class="mt-10">
                        @foreach ($leadArticle->categories as $category)
                            <span class="badge">{{ $category->name }}</span>
                        @endforeach
                    </div>
                    <div class="mt-10">
                        <a class="button" href="{{ route('articles.show', $leadArticle) }}">Read story</a>
                    </div>
                </article>

                <div class="story-list">
                    @forelse ($secondaryArticles as $article)
                        <article class="story-item">
                            <span class="eyebrow">Latest</span>
                            <h3 class="h3-tight"><a href="{{ route('articles.show', $article) }}">{{ $article->title }}</a></h3>
                            <div class="mini-meta">
                                {{ optional($article->published_at)->format('j M Y') ?: 'Draft' }}
                                @if ($article->author)
                                    · {{ $article->author->name }}
                                @endif
                            </div>
                            <p class="h3-tight">{{ $article->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->body), 120) }}</p>
                        </article>
                    @empty
                        <div class="empty-state">More editorial stories will appear here as articles are published.</div>
                    @endforelse

                    <div class="promo-card">
                        <span class="eyebrow">Advertise with us</span>
                        <h3 class="h3-block">Reach local readers with a featured listing or ad campaign.</h3>
                        <p class="muted">Get your business seen by Eastern Freestate residents through directory placement, banner ads, and push notifications.</p>
                        <a href="{{ route('advertise.index') }}" class="button mt-08">See advertising options</a>
                    </div>
                </div>
            </div>
        @else
            <div class="empty-state">No published articles yet. The homepage editorial lead block will populate automatically once stories are live.</div>
        @endif
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h2>Business Categories</h2>
                <p class="section-subtitle">Give people a quick, structured way into the directory before they even start searching.</p>
            </div>
            <a href="{{ route('directory.index') }}">Browse the directory</a>
        </div>
        <div class="category-grid">
            @forelse ($featuredCategories as $category)
                <a href="{{ route('search.index', ['category' => $category->slug]) }}" class="category-card">
                    <span class="eyebrow">Category</span>
                    <h3 class="h3-cat">{{ $category->name }}</h3>
                    <p class="muted h3-tight">{{ $category->visible_listings_count }} visible businesses</p>
                </a>
            @empty
                <div class="empty-state">Directory categories will appear here as the listing catalogue grows.</div>
            @endforelse
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h2>Featured Businesses</h2>
                <p class="section-subtitle">Support local — discover featured businesses from across the Eastern Freestate.</p>
            </div>
            <a href="{{ route('directory.index') }}">See all businesses</a>
        </div>
        @forelse ($featuredListings as $listing)
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card listing-card-home">
                @if ($listing->featured_image)
                    <img class="feature-media media-220" src="{{ \Illuminate\Support\Facades\Storage::url($listing->featured_image) }}" alt="{{ $listing->title }}">
                @endif
                @if ($listing->logo_path)
                    <img class="listing-logo" src="{{ \Illuminate\Support\Facades\Storage::url($listing->logo_path) }}" alt="{{ $listing->title }} logo">
                @endif
                <span class="eyebrow">{{ $listing->is_featured ? 'Featured business' : 'Local business' }}</span>
                <h3 class="h3-card"><a href="{{ route('directory.show', $listing) }}">{{ $listing->title }}</a></h3>
                <div class="mini-meta">{{ $listing->city ?: 'Location pending' }}{{ $listing->region ? ', '.$listing->region : '' }}</div>
                <p>{{ $listing->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $listing->description), 150) }}</p>
                <div>
                    @foreach ($listing->categories->take(3) as $category)
                        <span class="badge">{{ $category->name }}</span>
                    @endforeach
                </div>
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No featured businesses are visible yet.</div>
        @endforelse
    </section>

    @if (isset($homeAdCampaigns) && $homeAdCampaigns->isNotEmpty())
        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Sponsored Spotlight</h2>
                    <p class="section-subtitle">Promoted businesses and special offers from across the region.</p>
                </div>
            </div>
            <div class="grid grid-3">
                @foreach ($homeAdCampaigns as $ad)
                    <x-ad-campaign-card :campaign="$ad" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="section">
        <div class="promo-strip">
            <div class="promo-card">
                <span class="eyebrow">Advertise locally</span>
                <h2 class="h2-block">Put your business in front of local customers.</h2>
                <p class="muted">Reach thousands of local readers with a featured listing, banner campaign, or event promotion across the Eastern Freestate.</p>
                <div class="hero-actions mt-10">
                    <a href="{{ route('advertise.index') }}" class="button">Explore packages</a>
                    <a href="{{ route('directory.index') }}" class="button-link btn-soft">See listing examples</a>
                </div>
            </div>
            <div class="card">
                <span class="eyebrow">Why this matters</span>
                <ul class="list-spaced">
                    <li>Editorial traffic feeds business discovery</li>
                    <li>Premium listings get homepage exposure</li>
                    <li>Events get a clear discovery lane</li>
                    <li>Advertisers get visible upgrade points</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <h2>Upcoming Events</h2>
                <p class="section-subtitle">Discover what's on near you — from markets and festivals to community gatherings and business events.</p>
            </div>
            <a href="{{ route('events.index') }}">See all events</a>
        </div>
        @forelse ($upcomingEvents as $event)
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card event-card-home">
                @if ($event->featured_image)
                    <img class="feature-media media-220" src="{{ \Illuminate\Support\Facades\Storage::url($event->featured_image) }}" alt="{{ $event->title }}">
                @endif
                <span class="eyebrow">Upcoming event</span>
                <h3 class="h3-card"><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></h3>
                <div class="mini-meta">
                    {{ optional($event->start_at)->format('D, j M Y g:i A') ?: 'Date pending' }}
                    @if ($event->listing)
                        · {{ $event->listing->title }}
                    @endif
                </div>
                <p>{{ $event->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $event->description), 150) }}</p>
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No public events are live yet.</div>
        @endforelse
    </section>

    <section class="section">
        <div class="community-strip">
            <div class="card">
                <span class="eyebrow">Community marketplace</span>
                <h2 class="h2-block">Buy, sell, and connect with people in your community.</h2>
                <p class="muted">Browse free community classifieds, post an item for sale, and stay connected with your neighbours across the Eastern Freestate.</p>
                <a href="{{ route('classifieds.index') }}" class="button mt-09">Browse classifieds</a>
            </div>
            <div class="cta-grid">
                <div class="cta-card">
                    <span class="eyebrow">For businesses</span>
                    <h3 class="h3-cta">Promote your business</h3>
                    <p class="muted">Use packages, featured placement, and event add-ons to reach local traffic.</p>
                    <a href="{{ route('advertise.index') }}">Start here</a>
                </div>
                <div class="cta-card">
                    <span class="eyebrow">For readers</span>
                    <h3 class="h3-cta">Follow local stories</h3>
                    <p class="muted">Read the latest local coverage and use search to jump between content and discovery.</p>
                    <a href="{{ route('articles.index') }}">Read the news</a>
                </div>
                <div class="cta-card">
                    <span class="eyebrow">For account holders</span>
                    <h3 class="h3-cta">Manage your activity</h3>
                    <p class="muted">Track subscriptions, renewal options, and recent commerce activity.</p>
                    <a href="{{ auth()->check() ? route('account.index') : route('login') }}">{{ auth()->check() ? 'Open account' : 'Sign in' }}</a>
                </div>
            </div>
        </div>
    </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const btn = document.querySelector('[data-railway-admin-seed]');
            const status = document.querySelector('[data-railway-admin-seed-status]');
            if (!btn) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const endpoint = @json(route('bootstrap.admin'));
            const originalText = btn.textContent;

            const setStatus = (text) => {
                if (!status) return;
                status.textContent = text || '';
            };

            btn.addEventListener('click', async () => {
                btn.disabled = true;
                btn.textContent = 'Seeding…';
                setStatus('Creating admin account and signing you in…');

                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({}),
                        credentials: 'same-origin',
                    });

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.ok) {
                        setStatus(data.message || 'Failed to seed admin. Check environment variables and try again.');
                        btn.disabled = false;
                        btn.textContent = originalText;
                        return;
                    }

                    window.location.href = data.redirect || @json(route('admin.dashboard'));
                } catch (_) {
                    setStatus('Network error while seeding admin.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
        })();
    </script>
@endpush
