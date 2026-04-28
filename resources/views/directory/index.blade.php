@extends('layouts.public')

@section('title', 'Business Directory | Eastern Freestate')

@push('styles')
    <style>
        .directory-hero {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.8fr);
            margin-bottom: 2rem;
        }
        .directory-panel {
            padding: 1.8rem;
            border-radius: 24px;
            background:
                radial-gradient(circle at top right, rgba(147, 197, 253, 0.22), transparent 30%),
                linear-gradient(135deg, rgba(29, 78, 216, 0.10), rgba(255, 255, 255, 0.9));
            border: 1px solid var(--border);
        }
        html[data-theme="dark"] .directory-panel {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.14), transparent 30%),
                linear-gradient(135deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.96));
        }
        .directory-search-form {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: 1.2fr 1fr 1fr auto;
            align-items: end;
            margin-top: 1.25rem;
        }
        .chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 1rem;
        }
        .chip-link {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            font-size: 0.92rem;
        }
        .stats-strip {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(2, 1fr);
        }
        .stat-card {
            border-radius: 20px;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .stat-card strong {
            display: block;
            font-size: 1.8rem;
            line-height: 1;
            margin-bottom: 0.3rem;
        }
        .directory-layout {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(260px, 0.9fr) minmax(0, 2.1fr);
        }
        .directory-sidebar {
            display: grid;
            gap: 1rem;
            align-content: start;
        }
        .directory-results {
            display: grid;
            gap: 1rem;
        }
        .directory-results-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .listing-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .listing-card {
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            height: 100%;
        }
        .listing-cover {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 18px;
            background: #dbeafe;
        }
        .listing-logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
            border-radius: 16px;
            background: #fff;
            border: 1px solid var(--border);
            padding: 0.4rem;
        }
        .listing-top {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
        }
        .listing-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .rating-pill,
        .featured-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.28rem 0.65rem;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .rating-pill { background: rgba(15, 23, 42, 0.08); color: var(--text); }
        .featured-pill { background: rgba(29, 78, 216, 0.14); color: var(--primary-dark); }
        .contact-stack {
            display: grid;
            gap: 0.45rem;
            color: var(--muted);
            font-size: 0.92rem;
        }
        .sidebar-list {
            display: grid;
            gap: 0.6rem;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.8rem 0.9rem;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            text-decoration: none;
        }
        .map-placeholder {
            min-height: 180px;
            display: grid;
            place-items: center;
            text-align: center;
            border-radius: 18px;
            border: 1px dashed var(--border);
            background: rgba(29, 78, 216, 0.04);
            color: var(--muted);
        }
        @media (max-width: 980px) {
            .directory-hero,
            .directory-layout {
                grid-template-columns: 1fr;
            }
            .directory-search-form {
                grid-template-columns: 1fr;
            }
            .stats-strip {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 640px) {
            .stats-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="directory-hero">
        <div class="directory-panel">
            <span class="eyebrow">Business directory</span>
            <h1 style="font-size:clamp(2rem, 3.5vw, 3rem); line-height:1.08; margin:0.5rem 0 0.9rem;">Discover trusted Eastern Freestate businesses, ranked for visibility and built to convert local attention.</h1>
            <p class="section-subtitle" style="max-width:48rem;">
                Browse local businesses, filter by category or location, and discover the services you need across the Eastern Freestate. Featured listings appear first.
            </p>

            <form method="get" action="{{ route('directory.index') }}" class="directory-search-form">
                <div>
                    <label for="q">Search businesses</label>
                    <input id="q" name="q" value="{{ $filters['q'] }}" placeholder="Business name, service, or keyword">
                </div>
                <div>
                    <label for="location">Location</label>
                    <input id="location" name="location" value="{{ $filters['location'] }}" placeholder="Bethlehem, Clarens, Fouriesburg...">
                </div>
                <div>
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button class="button" type="submit">Search Directory</button>
                    <button type="button" class="chip-link" id="btn-near-me" style="margin-top:0.5rem; width:100%; justify-content:center; border-style:dashed;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.2rem; height:1.2rem; margin-right:0.4rem;">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                        Near me
                    </button>
                </div>
                <input type="hidden" name="user_lat" id="user_lat" value="{{ request('user_lat') }}">
                <input type="hidden" name="user_lng" id="user_lng" value="{{ request('user_lng') }}">
            </form>

            @push('scripts')
            <script>
                document.getElementById('btn-near-me').addEventListener('click', function() {
                    const btn = this;
                    const originalText = btn.innerHTML;
                    
                    if (!navigator.geolocation) {
                        alert('Geolocation is not supported by your browser');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = 'Locating...';

                    navigator.geolocation.getCurrentPosition(function(position) {
                        document.getElementById('user_lat').value = position.coords.latitude;
                        document.getElementById('user_lng').value = position.coords.longitude;
                        btn.closest('form').submit();
                    }, function(error) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        alert('Unable to retrieve your location: ' + error.message);
                    });
                });
            </script>
            @endpush

            <div class="chip-row">
                @foreach ($popularLocations as $location)
                    <a class="chip-link" href="{{ route('directory.index', ['location' => $location->city]) }}">{{ $location->city }} ({{ $location->listings_count }})</a>
                @endforeach
            </div>
        </div>

        <div class="stats-strip">
            <div class="stat-card">
                <strong>{{ $directoryStats['visible_listings'] }}</strong>
                <span>Visible businesses</span>
            </div>
            <div class="stat-card">
                <strong>{{ $directoryStats['featured_listings'] }}</strong>
                <span>Featured placements</span>
            </div>
            <div class="stat-card">
                <strong>{{ $directoryStats['categories'] }}</strong>
                <span>Business categories</span>
            </div>
            <div class="stat-card">
                <strong>{{ $directoryStats['results'] }}</strong>
                <span>Results for current filters</span>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="directory-layout">
            <aside class="directory-sidebar">
                <div class="card">
                    <span class="eyebrow">Quick filters</span>
                    <div class="chip-row" style="margin-top:0.75rem;">
                        <a class="chip-link" href="{{ route('directory.index') }}">Reset filters</a>
                        <a class="chip-link" href="{{ route('directory.index', array_filter(['q' => $filters['q'], 'location' => $filters['location'], 'category' => $filters['category'], 'featured' => 1])) }}">Featured only</a>
                    </div>

                    <form method="get" action="{{ route('directory.index') }}" style="margin-top:1rem;" class="stack">
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="location" value="{{ $filters['location'] }}">
                        <input type="hidden" name="category" value="{{ $filters['category'] }}">
                        <label style="display:flex; align-items:center; gap:0.65rem;">
                            <input type="checkbox" name="featured" value="1" @checked($filters['featured'])>
                            <span>Show featured businesses only</span>
                        </label>
                        <button class="button" type="submit">Apply sidebar filters</button>
                    </form>
                </div>

                <div class="card">
                    <div class="section-head" style="margin-bottom:0.9rem;">
                        <div>
                            <h3 style="margin:0;">Browse Categories</h3>
                        </div>
                    </div>
                    <div class="sidebar-list">
                        @foreach ($featuredCategories as $category)
                            <a class="sidebar-link" href="{{ route('directory.index', ['category' => $category->slug]) }}">
                                <span>{{ $category->name }}</span>
                                <span class="muted">{{ $category->visible_listings_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                @foreach ($sidebarAdCampaigns as $adCampaign)
                    <x-ad-campaign-card :campaign="$adCampaign" />
                @endforeach

                <div class="card">
                    <span class="eyebrow">Map view</span>
                    <div style="margin-top:0.85rem; border-radius:16px; overflow:hidden;">
                        <x-map-embed map-id="directory-map" :markers="$mapMarkers" height="260px" />
                    </div>
                    <p class="muted" style="font-size:0.82rem; margin:0.5rem 0 0;">
                        {{ count($mapMarkers) }} {{ Str::plural('location', count($mapMarkers)) }} mapped for current filters.
                    </p>
                </div>

                <div class="card">
                    <span class="eyebrow">Get listed</span>
                    <h3 style="margin:0.45rem 0 0.6rem;">Turn directory traffic into business visibility.</h3>
                    <p class="muted">Premium placement, package-backed visibility, and event eligibility all start here.</p>
                    <div class="chip-row" style="margin-top:0.9rem;">
                        <a class="button" href="{{ route('add-listing.index') }}">Start listing</a>
                        <a class="chip-link" href="{{ route('advertise.index') }}">Advertise</a>
                    </div>
                </div>
            </aside>

            <div class="directory-results">
                <div class="directory-results-header">
                    <div>
                        <h2 style="margin:0;">Directory Results</h2>
                        <p class="section-subtitle">{{ $listings->total() }} {{ Str::plural('business', $listings->total()) }} match your current filters.</p>
                    </div>
                    <div class="muted">{{ $listings->total() }} businesses found</div>
                </div>

                @forelse ($listings as $listing)
                    @if ($loop->first)<div class="listing-grid">@endif
                    <article class="card listing-card">
                        @if ($listing->featured_image)
                            <img class="listing-cover" src="{{ \Illuminate\Support\Facades\Storage::url($listing->featured_image) }}" alt="{{ $listing->title }}">
                        @endif

                        <div class="listing-top">
                            <div>
                                <div class="listing-meta">
                                    <span>{{ $listing->city ?: 'Location pending' }}{{ $listing->region ? ', '.$listing->region : '' }}</span>
                                    @isset($listing->distance)
                                        <span class="badge" style="background:rgba(5, 150, 105, 0.12); color:#059669; border:0;">{{ number_format($listing->distance, 1) }} km away</span>
                                    @endisset
                                    @if ($listing->is_featured)
                                        <span class="featured-pill">Featured</span>
                                    @endif
                                </div>
                                <h3 style="margin:0.45rem 0 0.2rem;"><a href="{{ route('directory.show', $listing) }}">{{ $listing->title }}</a></h3>
                            </div>
                            @if ($listing->logo_path)
                                <img class="listing-logo" src="{{ \Illuminate\Support\Facades\Storage::url($listing->logo_path) }}" alt="{{ $listing->title }} logo">
                            @endif
                        </div>

                        <div class="listing-meta">
                            <span class="rating-pill">
                                {{ $listing->reviews_avg_rating ? number_format((float) $listing->reviews_avg_rating, 1).' / 5' : 'No rating yet' }}
                            </span>
                            <span>{{ $listing->reviews_count }} reviews</span>
                            @if ($listing->website_url)
                                <span>Website available</span>
                            @endif
                        </div>

                        <p style="margin:0;">{{ $listing->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $listing->description), 170) }}</p>

                        <div>
                            @foreach ($listing->categories->take(3) as $category)
                                <span class="badge">{{ $category->name }}</span>
                            @endforeach
                        </div>

                        <div class="contact-stack">
                            @if ($listing->phone)
                                <span>Phone: {{ $listing->phone }}</span>
                            @endif
                            @if ($listing->email)
                                <span>Email: {{ $listing->email }}</span>
                            @endif
                            @if ($listing->address_line)
                                <span>Address: {{ $listing->address_line }}</span>
                            @endif
                        </div>

                        <div class="chip-row" style="margin-top:auto;">
                            <a class="button" href="{{ route('directory.show', $listing) }}">View business</a>
                            @if ($listing->website_url)
                                <a class="chip-link" href="{{ $listing->website_url }}" target="_blank" rel="noreferrer">Visit website</a>
                            @endif
                        </div>
                    </article>
                    @if ($loop->last)</div>@endif
                @empty
                    <div class="empty-state">No listings match your current filters.</div>
                @endforelse

                <div style="margin-top: 1rem;">{{ $listings->links() }}</div>
            </div>
        </div>
    </section>
@endsection
