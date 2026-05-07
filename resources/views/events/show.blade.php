@extends('layouts.public')

@section('title', $event->title.' | Events')

@push('styles')
    <style>
        .event-detail-hero {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.85fr);
            margin-bottom: 2rem;
        }
        .event-detail-panel {
            padding: 1.8rem;
            border-radius: 24px;
            border: 1px solid var(--border);
            background:
                radial-gradient(circle at top right, rgba(147, 197, 253, 0.18), transparent 30%),
                linear-gradient(135deg, rgba(29, 78, 216, 0.08), rgba(255, 255, 255, 0.92));
        }
        html[data-theme="dark"] .event-detail-panel {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.14), transparent 30%),
                linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.98));
        }
        .event-detail-cover {
            width: 100%;
            height: 360px;
            object-fit: cover;
            border-radius: 22px;
            background: #dbeafe;
            margin-top: 1.25rem;
        }
        .event-pills,
        .event-info-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            color: var(--muted);
            font-size: 0.92rem;
        }
        .event-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.34rem 0.74rem;
            font-size: 0.82rem;
            font-weight: 700;
            background: rgba(29, 78, 216, 0.14);
            color: var(--primary-dark);
        }
        .event-stat-grid {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(3, 1fr);
        }
        .event-stat {
            border-radius: 18px;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .event-stat strong {
            display: block;
            font-size: 1.35rem;
            line-height: 1.1;
            margin-bottom: 0.35rem;
        }
        .event-detail-layout {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.95fr);
        }
        .event-detail-stack {
            display: grid;
            gap: 1rem;
        }
        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .chip-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.8rem 1.05rem;
            background: rgba(29, 78, 216, 0.10);
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            gap: 0.85rem;
        }
        .info-item,
        .related-item {
            padding: 0.95rem 1rem;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .map-box {
            min-height: 190px;
            display: grid;
            place-items: center;
            text-align: center;
            border-radius: 18px;
            border: 1px dashed var(--border);
            background: rgba(29, 78, 216, 0.04);
            color: var(--muted);
        }
        .promo-zone {
            padding: 1.3rem;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(29, 78, 216, 0.08), rgba(255,255,255,0.94));
        }
        html[data-theme="dark"] .promo-zone {
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.08), rgba(15,23,42,0.9));
        }
        .related-list {
            display: grid;
            gap: 0.9rem;
        }
        @media (max-width: 980px) {
            .event-detail-hero,
            .event-detail-layout {
                grid-template-columns: 1fr;
            }
            .event-stat-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
        @media (max-width: 640px) {
            .event-stat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="event-detail-hero">
        <div class="event-detail-panel">
            <span class="eyebrow">Event profile</span>
            <div class="event-pills" style="margin-top:0.75rem;">
                <span>{{ optional($event->start_at)->format('D j M Y g:i A') ?: 'Date pending' }}</span>
                @if ($event->end_at)
                    <span>Ends {{ $event->end_at->format('D j M Y g:i A') }}</span>
                @endif
                @if ($event->is_all_day)
                    <span class="event-pill">All day</span>
                @endif
                @if ($event->listing)
                    <span class="event-pill">Hosted by {{ $event->listing->title }}</span>
                @endif
            </div>

            <h1 style="font-size:clamp(2rem, 3.5vw, 3rem); line-height:1.08; margin:0.65rem 0 0.8rem;">{{ $event->title }}</h1>
            <p class="section-subtitle" style="max-width:48rem;">
                {{ $event->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $event->description), 220) }}
            </p>
            <div style="margin-top:0.9rem;">
                @foreach ($event->categories as $category)
                    <span class="badge">{{ $category->name }}</span>
                @endforeach
            </div>
            <div class="action-row">
                @if ($event->website_url)
                    <a class="button" href="{{ $event->website_url }}" target="_blank" rel="noreferrer">Visit event website</a>
                @endif
                @if ($event->listing)
                    <a class="chip-link" href="{{ route('directory.show', $event->listing) }}">View organiser business</a>
                @endif
            </div>

            @if ($event->featured_image)
                <img class="event-detail-cover" src="{{ \Illuminate\Support\Facades\Storage::url($event->featured_image) }}" alt="{{ $event->title }}" decoding="async" fetchpriority="high">
            @endif
        </div>

        <div class="event-stat-grid">
            <div class="event-stat">
                <strong>{{ optional($eventStats['starts'])->format('j M') ?: '-' }}</strong>
                <span>Starts</span>
            </div>
            <div class="event-stat">
                <strong>{{ $eventStats['is_all_day'] ? 'All day' : (optional($eventStats['ends'])->format('j M') ?: 'Timed') }}</strong>
                <span>Schedule</span>
            </div>
            <div class="event-stat">
                <strong>{{ $eventStats['organiser'] ?: '-' }}</strong>
                <span>Organiser</span>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="event-detail-layout">
            <div class="event-detail-stack">
                <article class="card">
                    <div class="section-head">
                        <div>
                            <h2>About This Event</h2>
                            <p class="section-subtitle">Full details including date, venue, organiser, and everything you need to plan your visit.</p>
                        </div>
                    </div>
                    <div style="margin-top:0.75rem;">{!! nl2br(e($event->description ?: $event->excerpt ?: 'Event details coming soon.')) !!}</div>
                </article>

                <article class="card">
                    <div class="section-head">
                        <div>
                            <h2>Venue and Schedule</h2>
                            <p class="section-subtitle">Where to go, when to arrive, and what to expect on the day.</p>
                        </div>
                    </div>
                    <div class="info-grid" style="margin-top:0.9rem;">
                        <div class="info-item"><strong>Venue</strong><br>{{ $event->venue_name ?: 'To be confirmed' }}</div>
                        <div class="info-item"><strong>Address</strong><br>{{ $event->address_line ?: 'To be confirmed' }}</div>
                        <div class="info-item"><strong>City / Region</strong><br>{{ $event->city ?: 'To be confirmed' }}{{ $event->region ? ', '.$event->region : '' }}</div>
                        <div class="info-item"><strong>Schedule</strong><br>
                            {{ optional($event->start_at)->format('j M Y g:i A') ?: 'Date pending' }}
                            @if ($event->end_at)
                                to {{ $event->end_at->format('j M Y g:i A') }}
                            @endif
                        </div>
                    </div>
                </article>

                <article class="card">
                    <div class="section-head">
                        <div>
                            <h2>Related Events</h2>
                            <p class="section-subtitle">Other events happening nearby or from the same organiser.</p>
                        </div>
                        <a href="{{ route('events.index') }}">Browse all events</a>
                    </div>
                    <div class="related-list" style="margin-top:0.9rem;">
                        @forelse ($relatedEvents as $relatedEvent)
                            <div class="related-item">
                                <div class="event-info-list">
                                    <span>{{ optional($relatedEvent->start_at)->format('j M Y g:i A') ?: 'Date pending' }}</span>
                                    @if ($relatedEvent->listing)
                                        <span>{{ $relatedEvent->listing->title }}</span>
                                    @endif
                                </div>
                                <h3 style="margin:0.45rem 0 0.35rem;"><a href="{{ route('events.show', $relatedEvent) }}">{{ $relatedEvent->title }}</a></h3>
                                <p style="margin:0;">{{ \Illuminate\Support\Str::limit($relatedEvent->excerpt ?: strip_tags((string) $relatedEvent->description), 110) }}</p>
                            </div>
                        @empty
                            <div class="empty-state">No related events found yet.</div>
                        @endforelse
                    </div>
                </article>
            </div>

            <aside class="event-detail-stack">
                <article class="card">
                    <h3>Event Details</h3>
                    <div class="info-grid" style="margin-top:0.9rem;">
                        <div class="info-item"><strong>Website / Ticket link</strong><br>
                            @if($event->website_url)
                                <a href="{{ $event->website_url }}" target="_blank" rel="noreferrer" class="text-primary hover:underline" style="color:var(--primary-dark); word-break:break-all;">{{ $event->website_url }}</a>
                            @else
                                Not available yet
                            @endif
                        </div>
                        <div class="info-item"><strong>Hosted by</strong><br>
                            @if ($event->listing)
                                <a href="{{ route('directory.show', $event->listing) }}">{{ $event->listing->title }}</a>
                            @else
                                No linked listing yet
                            @endif
                        </div>
                        <div class="info-item"><strong>Organiser categories</strong><br>
                            @if ($event->listing && $event->listing->categories->isNotEmpty())
                                {{ $event->listing->categories->pluck('name')->join(', ') }}
                            @else
                                Not available yet
                            @endif
                        </div>
                    </div>
                </article>

                <article class="card">
                    <h3>Map &amp; Directions</h3>
                    @php
                        $mapLat = $event->latitude ?? $event->listing?->latitude;
                        $mapLng = $event->longitude ?? $event->listing?->longitude;
                        $mapLabel = $event->venue_name ?? $event->title;
                    @endphp
                    @if ($mapLat && $mapLng)
                        <div style="margin-top:0.9rem; border-radius:14px; overflow:hidden;">
                            <x-map-embed
                                map-id="event-map"
                                :lat="(float) $mapLat"
                                :lng="(float) $mapLng"
                                :label="$mapLabel"
                                height="260px"
                            />
                        </div>
                        <a class="chip-link"
                           style="margin-top:0.75rem; display:inline-flex;"
                           href="https://www.openstreetmap.org/directions?from=&to={{ $mapLat }},{{ $mapLng }}"
                           target="_blank" rel="noreferrer">
                            Get directions &rarr;
                        </a>
                    @else
                        <div class="map-box" style="margin-top:0.9rem;">
                            <div>
                                <strong>Venue not yet mapped</strong>
                                <p style="margin:0.5rem 0 0;">Use the venue address above to navigate with your preferred directions app.</p>
                            </div>
                        </div>
                    @endif
                </article>

                <article class="promo-zone">
                    <span class="eyebrow">Promote your event</span>
                    <h3 style="margin:0.45rem 0 0.6rem;">Give your event more local visibility with a promotion package.</h3>
                    <p class="muted">Want to promote your own event? Start with a business directory listing, then add an event promotion slot to reach local audiences.</p>
                    <div class="action-row">
                        @if ($event->listing && $event->listing->hasActiveBusinessEntitlement())
                            <a class="button" href="{{ route('checkout.index', ['event' => $event->slug]) }}">Buy event package</a>
                        @endif
                        <a class="chip-link" href="{{ route('advertise.index') }}">Advertise event</a>
                    </div>
                </article>

                @foreach ($sidebarAdCampaigns as $ad)
                    <x-ad-campaign-card :campaign="$ad" />
                @endforeach
            </aside>
        </div>
    </section>
@endsection
