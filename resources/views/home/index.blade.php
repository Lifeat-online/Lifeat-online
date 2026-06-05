@extends('layouts.public')

@section('title', 'Eastern Freestate | Home')
@section('meta_description', 'Life@ connects Eastern Freestate residents with local news, trusted businesses, upcoming events, vouchers, classifieds, civic fault reporting, and community opportunities.')
@section('canonical_url', route('home'))

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
                radial-gradient(900px circle at 14% 18%, rgba(245, 197, 104, 0.28), transparent 40%),
                radial-gradient(800px circle at 88% 20%, rgba(74, 190, 143, 0.26), transparent 38%),
                radial-gradient(900px circle at 62% 110%, rgba(231, 151, 92, 0.24), transparent 45%),
                linear-gradient(135deg, #241710, #6f3320 55%, #123d32);
            color: #fff8ec;
            box-shadow: 0 26px 60px rgba(77, 47, 24, 0.28);
        }
        html[data-theme="dark"] .hero-panel {
            background:
                radial-gradient(900px circle at 18% 18%, rgba(231, 151, 92, 0.18), transparent 40%),
                radial-gradient(900px circle at 82% 22%, rgba(74, 190, 143, 0.15), transparent 40%),
                linear-gradient(135deg, #151210, #2b1f19 55%, #10372e);
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
            gap: 0.5rem;
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
        .community-strip,
        .mission-strip {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: stretch;
        }
        .mission-card {
            border-radius: 24px;
            padding: 1.6rem;
            border: 1px solid var(--border);
            background:
                linear-gradient(135deg, rgba(20, 184, 166, 0.12), rgba(29, 78, 216, 0.04)),
                var(--surface);
        }
        .mission-list {
            display: grid;
            gap: 0.75rem;
            margin: 1rem 0 0;
            padding: 0;
            list-style: none;
        }
        .mission-list li {
            display: grid;
            gap: 0.25rem;
            padding: 0.85rem;
            border-radius: 16px;
            border: 1px solid rgb(var(--border-rgb) / 0.9);
            background: rgb(var(--surface-rgb) / 0.76);
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
        @media (max-width: 960px) {
            .home-hero,
            .lead-grid,
            .promo-strip,
            .mission-strip,
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
            <span class="hero-kicker">A local guide that creates local work</span>
            <h1 class="hero-heading">Local news, trusted businesses, community events, and paid work for people in our towns.</h1>
            <p class="hero-copy">
                Life@ News is built as a job-creation platform first. Every listing, story, event, and advert helps grow a useful local information network while giving writers and sales staff real ways to earn.
            </p>
            <div class="hero-actions">
                <a href="{{ route('directory.index') }}" class="hero-primary">Browse Directory <x-icon name="arrow-right" class="w-4 h-4" /></a>
                <a href="{{ route('events.index') }}" class="hero-secondary">See Upcoming Events <x-icon name="arrow-right" class="w-4 h-4" /></a>
                <a href="{{ route('advertise.index') }}" class="hero-secondary">Create local work <x-icon name="arrow-right" class="w-4 h-4" /></a>
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
                <span><a href="{{ route('add-listing.index') }}">Add your listing and support jobs</a></span>
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

    <section class="section mission-strip">
        <article class="mission-card">
            <span class="eyebrow">Why the platform exists</span>
            <h2 class="h2-block">Advertising here funds local earning opportunities.</h2>
            <p class="muted">
                Businesses get visibility, residents get useful local information, and the work of capturing listings, writing stories, assisting clients, and running campaigns creates income for people in the community.
            </p>
            <div class="hero-actions mt-10">
                <a href="{{ route('advertise.index') }}" class="button">See how advertising creates work</a>
                <a href="{{ route('staff-signup.create') }}" class="button-link">Apply to work with us</a>
            </div>
        </article>
        <aside class="card">
            <span class="eyebrow">Staff assisted vs self service</span>
            <ul class="mission-list">
                <li>
                    <strong>Staff assisted costs less because it creates a job.</strong>
                    <span class="muted">A local person helps capture, prepare, and support the business listing.</span>
                </li>
                <li>
                    <strong>Self service costs more because it skips that work opportunity.</strong>
                    <span class="muted">Owners get direct control, while the higher price helps protect the job-creation model.</span>
                </li>
                <li>
                    <strong>Every add-on starts with a listing.</strong>
                    <span class="muted">Events, banners, article placements, and push campaigns all grow from a verified local business profile.</span>
                </li>
            </ul>
        </aside>
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
                        <img class="feature-media" src="{{ '/media/'.ltrim($leadArticle->featured_image, '/') }}" alt="{{ $leadArticle->localizedTitle() }}" decoding="async" fetchpriority="high">
                    @else
                        <div class="media-fallback" aria-hidden="true"></div>
                    @endif
                    <span class="eyebrow">Lead story</span>
                    <h3 class="lead-title">
                        <a href="{{ route('articles.show', $leadArticle) }}">{{ $leadArticle->localizedTitle() }}</a>
                    </h3>
                    <div class="mini-meta">
                        {{ optional($leadArticle->published_at)->format('j M Y') ?: 'Draft' }}
                        @if ($leadArticle->author)
                            · {{ $leadArticle->author->name }}
                        @endif
                    </div>
                    <p class="mt-08">{{ $leadArticle->localizedExcerpt() ?: \Illuminate\Support\Str::limit(strip_tags((string) $leadArticle->localizedBody()), 240) }}</p>
                    <div class="mt-10">
                        @foreach ($leadArticle->categories as $category)
                            <span class="badge">{{ $category->localizedValue('name') }}</span>
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
                            <h3 class="h3-tight"><a href="{{ route('articles.show', $article) }}">{{ $article->localizedTitle() }}</a></h3>
                            <div class="mini-meta">
                                {{ optional($article->published_at)->format('j M Y') ?: 'Draft' }}
                                @if ($article->author)
                                    · {{ $article->author->name }}
                                @endif
                            </div>
                            <p class="h3-tight">{{ $article->localizedExcerpt() ?: \Illuminate\Support\Str::limit(strip_tags((string) $article->localizedBody()), 120) }}</p>
                        </article>
                    @empty
                        <div class="empty-state">More editorial stories will appear here as articles are published.</div>
                    @endforelse

                    <div class="promo-card">
                        <span class="eyebrow">Advertise with us</span>
                        <h3 class="h3-block">Reach local readers and help create local work.</h3>
                        <p class="muted">Start with a business listing, then add events, banner ads, article placements, and push notifications. Staff-assisted listings are intentionally cheaper because they put someone to work.</p>
                        <a href="{{ route('advertise.index') }}" class="button mt-08">Build a visibility package</a>
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
                <a href="{{ route('search.index', ['category' => $category['slug']]) }}" class="category-card">
                    <span class="eyebrow">Category</span>
                    <h3 class="h3-cat">{{ $category['name'] }}</h3>
                    <p class="muted h3-tight">{{ $category['visible_listings_count'] }} visible businesses</p>
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
                    <img class="feature-media media-220" src="{{ \Illuminate\Support\Facades\Storage::url($listing->featured_image) }}" alt="{{ $listing->localizedValue('title') }}" loading="lazy" decoding="async">
                @endif
                @if ($listing->logo_path)
                    <img class="listing-logo" src="{{ \Illuminate\Support\Facades\Storage::url($listing->logo_path) }}" alt="{{ $listing->localizedValue('title') }} logo" loading="lazy" decoding="async">
                @endif
                <span class="eyebrow">{{ $listing->is_featured ? 'Featured business' : 'Local business' }}</span>
                <h3 class="h3-card"><a href="{{ route('directory.show', $listing) }}">{{ $listing->localizedValue('title') }}</a></h3>
                <div class="mini-meta">{{ $listing->localizedValue('city') ?: 'Location pending' }}{{ $listing->localizedValue('region') ? ', '.$listing->localizedValue('region') : '' }}</div>
                <p>{{ $listing->localizedValue('excerpt') ?: \Illuminate\Support\Str::limit(strip_tags((string) $listing->localizedValue('description')), 150) }}</p>
                <div>
                    @foreach ($listing->categories->take(3) as $category)
                        <span class="badge">{{ $category->localizedValue('name') }}</span>
                    @endforeach
                </div>
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No featured businesses are visible yet.</div>
        @endforelse
    </section>

    @if (isset($featuredVouchers) && $featuredVouchers->isNotEmpty())
        <section class="section">
            <div class="section-head" data-reveal>
                <div>
                    <h2>Voucher Deals</h2>
                    <p class="section-subtitle">Save with limited-time offers from businesses in the directory.</p>
                </div>
                <a href="{{ route('vouchers.index') }}">View all vouchers</a>
            </div>
            <div class="grid grid-2">
                @foreach ($featuredVouchers as $voucher)
                    <x-voucher-card :voucher="$voucher" />
                @endforeach
            </div>
        </section>
    @endif

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
                <h2 class="h2-block">Put your business in front of local customers while backing local jobs.</h2>
                <p class="muted">Choose staff-assisted setup to support paid local sales and onboarding work, or self-service if you want direct control. Both paths start with a listing and unlock events, banners, article placements, and push campaigns.</p>
                <div class="hero-actions mt-10">
                    <a href="{{ route('advertise.index') }}" class="button">Explore job-creating packages</a>
                    <a href="{{ route('directory.index') }}" class="button-link btn-soft">See listing examples</a>
                </div>
            </div>
            <div class="card">
                <span class="eyebrow">Why this matters</span>
                <ul class="list-spaced">
                    <li>Staff-assisted setup gives local people paid work</li>
                    <li>Editorial traffic feeds business discovery</li>
                    <li>Events and campaigns grow from verified listings</li>
                    <li>Advertising revenue helps fund local content and support</li>
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
                    <img class="feature-media media-220" src="{{ \Illuminate\Support\Facades\Storage::url($event->featured_image) }}" alt="{{ $event->localizedValue('title') }}" loading="lazy" decoding="async">
                @endif
                <span class="eyebrow">Upcoming event</span>
                <h3 class="h3-card"><a href="{{ route('events.show', $event) }}">{{ $event->localizedValue('title') }}</a></h3>
                <div class="mini-meta">
                    {{ optional($event->start_at)->format('D, j M Y g:i A') ?: 'Date pending' }}
                    @if ($event->listing)
                        · {{ $event->listing->localizedValue('title') }}
                    @endif
                </div>
                <p>{{ $event->localizedValue('excerpt') ?: \Illuminate\Support\Str::limit(strip_tags((string) $event->localizedValue('description')), 150) }}</p>
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
                    <h3 class="h3-cta">Promote your business and create work</h3>
                    <p class="muted">Use staff-assisted or self-service packages, featured placements, events, and campaign add-ons to reach local traffic.</p>
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
