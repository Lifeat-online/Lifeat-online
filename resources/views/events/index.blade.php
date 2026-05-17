@extends('layouts.public')

@section('title', 'Events | Eastern Freestate')

@push('styles')
    <style>
        .events-hero {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.8fr);
            margin-bottom: 2rem;
        }
        .events-panel {
            padding: 1.8rem;
            border-radius: 24px;
            background:
                radial-gradient(circle at top right, rgba(147, 197, 253, 0.22), transparent 30%),
                linear-gradient(135deg, rgba(29, 78, 216, 0.10), rgba(255, 255, 255, 0.9));
            border: 1px solid var(--border);
        }
        html[data-theme="dark"] .events-panel {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.14), transparent 30%),
                linear-gradient(135deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.96));
        }
        .events-search-form {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: 1.2fr 1fr 1fr auto;
            align-items: end;
            margin-top: 1.25rem;
        }
        .stats-strip-events {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(2, 1fr);
        }
        .stat-card-events {
            border-radius: 20px;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .stat-card-events strong {
            display: block;
            font-size: 1.8rem;
            line-height: 1;
            margin-bottom: 0.3rem;
        }
        .events-layout {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(260px, 0.9fr) minmax(0, 2.1fr);
        }
        .events-sidebar {
            display: grid;
            gap: 1rem;
            align-content: start;
        }
        .events-results {
            display: grid;
            gap: 1rem;
        }
        .events-results-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .event-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .event-card {
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            height: 100%;
        }
        .event-cover {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 18px;
            background: #dbeafe;
        }
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .event-date-pill,
        .event-host-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.3rem 0.7rem;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .event-date-pill {
            background: rgba(15, 23, 42, 0.08);
            color: var(--text);
        }
        .event-host-pill {
            background: rgba(29, 78, 216, 0.14);
            color: var(--primary-dark);
        }
        .sidebar-list-events {
            display: grid;
            gap: 0.6rem;
        }
        .sidebar-link-events {
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
        .event-map-placeholder {
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
            .events-hero,
            .events-layout {
                grid-template-columns: 1fr;
            }
            .events-search-form {
                grid-template-columns: 1fr;
            }
            .stats-strip-events {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 640px) {
            .stats-strip-events {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="events-hero">
        <div class="events-panel">
            <span class="eyebrow">Events directory</span>
            <h1 style="font-size:clamp(2rem, 3.5vw, 3rem); line-height:1.08; margin:0.5rem 0 0.9rem;">Discover local events that are tied to real businesses and built for Eastern Freestate visibility.</h1>
            <p class="section-subtitle" style="max-width:48rem;">
                Find upcoming events in your area, browse by category or location, and discover what local businesses are hosting near you.
            </p>

            <form method="get" action="{{ route('events.index') }}" class="events-search-form">
                <div>
                    <label for="q">Search events</label>
                    <input id="q" name="q" value="{{ $filters['q'] }}" placeholder="Event title, organiser, venue, or keyword">
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
                            <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->localizedValue('name') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button class="button" type="submit">Search Events</button>
                    <button type="button" class="chip-link" id="btn-near-me" style="margin-top:0.5rem; width:100%; justify-content:center;">
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
                    <a class="chip-link" href="{{ route('events.index', ['location' => $location->city]) }}">{{ $location->city }} ({{ $location->events_count }})</a>
                @endforeach
            </div>
        </div>

        <div class="stats-strip-events">
            <div class="stat-card-events">
                <strong>{{ $eventStats['visible_events'] }}</strong>
                <span>Visible events</span>
            </div>
            <div class="stat-card-events">
                <strong>{{ $eventStats['upcoming_events'] }}</strong>
                <span>Upcoming events</span>
            </div>
            <div class="stat-card-events">
                <strong>{{ $eventStats['categories'] }}</strong>
                <span>Event categories</span>
            </div>
            <div class="stat-card-events">
                <strong>{{ $eventStats['results'] }}</strong>
                <span>Results for current filters</span>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="events-layout">
            <aside class="events-sidebar">
                <div class="card">
                    <span class="eyebrow">Quick filters</span>
                    <div class="chip-row" style="margin-top:0.75rem;">
                        <a class="chip-link" href="{{ route('events.index') }}">Reset filters</a>
                        <a class="chip-link" href="{{ route('events.index', array_filter(['q' => $filters['q'], 'location' => $filters['location'], 'category' => $filters['category'], 'upcoming' => 1])) }}">Upcoming only</a>
                    </div>

                    <form method="get" action="{{ route('events.index') }}" style="margin-top:1rem;" class="stack">
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="location" value="{{ $filters['location'] }}">
                        <input type="hidden" name="category" value="{{ $filters['category'] }}">
                        <label style="display:flex; align-items:center; gap:0.65rem;">
                            <input type="checkbox" name="upcoming" value="1" @checked($filters['upcoming'])>
                            <span>Show upcoming events only</span>
                        </label>
                        <button class="button" type="submit">Apply sidebar filters</button>
                    </form>
                </div>

                <div class="card">
                    <div class="section-head" style="margin-bottom:0.9rem;">
                        <div>
                            <h3 style="margin:0;">Browse Event Categories</h3>
                        </div>
                    </div>
                    <div class="sidebar-list-events">
                        @foreach ($featuredCategories as $category)
                            <a class="sidebar-link-events" href="{{ route('events.index', ['category' => $category->slug]) }}">
                                <span>{{ $category->localizedValue('name') }}</span>
                                <span class="muted">{{ $category->visible_events_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                @foreach ($sidebarAdCampaigns as $ad)
                    <x-ad-campaign-card :campaign="$ad" />
                @endforeach

                <div class="card">
                    <span class="eyebrow">Map view</span>
                    <div style="margin-top:0.85rem; border-radius:16px; overflow:hidden;">
                        <x-map-embed map-id="events-map" :markers="$mapMarkers" height="260px" />
                    </div>
                    <p class="muted" style="font-size:0.82rem; margin:0.5rem 0 0;">
                        {{ count($mapMarkers) }} {{ Str::plural('event', count($mapMarkers)) }} mapped for current filters.
                    </p>
                </div>

                <div class="card">
                    <span class="eyebrow">Promote an event</span>
                    <h3 style="margin:0.45rem 0 0.6rem;">Hosting a local event? Get it in front of the right audience.</h3>
                    <p class="muted">Events on the platform are tied to local business listings. Start with a directory package and add event promotion from there.</p>
                    <div class="chip-row" style="margin-top:0.9rem;">
                        <a class="button" href="{{ route('checkout.index') }}">View packages</a>
                        <a class="chip-link" href="{{ route('directory.index') }}">Browse organisers</a>
                    </div>
                </div>
            </aside>

            <div class="events-results">
                <div class="events-results-header">
                    <div>
                        <h2 style="margin:0;">Event Results</h2>
                        <p class="section-subtitle">{{ $events->total() }} {{ Str::plural('event', $events->total()) }} match your current filters.</p>
                    </div>
                    <div class="muted">{{ $events->total() }} events found</div>
                </div>

                @forelse ($events as $event)
                    @if ($loop->first)<div class="event-grid">@endif
                    <article class="card event-card">
                        @if ($event->featured_image)
                            <img class="event-cover" src="{{ \Illuminate\Support\Facades\Storage::url($event->featured_image) }}" alt="{{ $event->localizedValue('title') }}" loading="lazy" decoding="async">
                        @endif

                        <div class="event-meta">
                            <span class="event-date-pill">{{ optional($event->start_at)->format('D j M') ?: 'Date pending' }}</span>
                            @isset($event->distance)
                                <span class="badge" style="background:rgba(5, 150, 105, 0.12); color:#059669; border:0; margin:0;">{{ number_format($event->distance, 1) }} km away</span>
                            @endisset
                            @if ($event->listing)
                                <span class="event-host-pill">Hosted by {{ $event->listing->localizedValue('title') }}</span>
                            @endif
                        </div>

                        <div>
                            <h3 style="margin:0 0 0.35rem;"><a href="{{ route('events.show', $event) }}">{{ $event->localizedValue('title') }}</a></h3>
                            <div class="event-meta">
                                <span>{{ optional($event->start_at)->format('j M Y g:i A') ?: 'Date pending' }}</span>
                                @if ($event->venue_name)
                                    <span>{{ $event->localizedValue('venue_name') }}</span>
                                @endif
                                @if ($event->city)
                                    <span>{{ $event->localizedValue('city') }}{{ $event->localizedValue('region') ? ', '.$event->localizedValue('region') : '' }}</span>
                                @endif
                            </div>
                        </div>

                        <p style="margin:0;">{{ $event->localizedValue('excerpt') ?: \Illuminate\Support\Str::limit(strip_tags((string) $event->localizedValue('description')), 170) }}</p>

                        <div>
                            @foreach ($event->categories->take(3) as $category)
                                <span class="badge">{{ $category->localizedValue('name') }}</span>
                            @endforeach
                        </div>

                        <div class="chip-row" style="margin-top:auto;">
                            <a class="button" href="{{ route('events.show', $event) }}">View event</a>
                            @if ($event->listing)
                                <a class="chip-link" href="{{ route('directory.show', $event->listing) }}">View organiser</a>
                            @endif
                        </div>
                    </article>
                    @if ($loop->last)</div>@endif
                @empty
                    <div class="empty-state">No events match your current filters.</div>
                @endforelse

                <div style="margin-top: 1rem;">{{ $events->links() }}</div>
            </div>
        </div>
    </section>
@endsection
