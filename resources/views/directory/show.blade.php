@extends('layouts.public')

@section('title', $listing->title.' | Directory')

@push('styles')
    <style>
        .listing-detail-hero {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.85fr);
            margin-bottom: 2rem;
        }
        .detail-panel {
            padding: 1.8rem;
            border-radius: 24px;
            border: 1px solid var(--border);
            background:
                radial-gradient(circle at top right, rgba(147, 197, 253, 0.18), transparent 30%),
                linear-gradient(135deg, rgba(29, 78, 216, 0.08), rgba(255, 255, 255, 0.92));
        }
        html[data-theme="dark"] .detail-panel {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.14), transparent 30%),
                linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.98));
        }
        .detail-cover {
            width: 100%;
            height: 360px;
            object-fit: cover;
            border-radius: 22px;
            background: #dbeafe;
            margin-top: 1.25rem;
        }
        .detail-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            border-radius: 18px;
            background: #fff;
            border: 1px solid var(--border);
            padding: 0.45rem;
        }
        .detail-top {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
        }
        .detail-meta,
        .detail-info-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            color: var(--muted);
            font-size: 0.92rem;
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
        .featured-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.3rem 0.7rem;
            font-size: 0.82rem;
            font-weight: 700;
            background: rgba(29, 78, 216, 0.14);
            color: var(--primary-dark);
        }
        .detail-stat-grid {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(3, 1fr);
        }
        .detail-stat {
            border-radius: 18px;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .detail-stat strong {
            display: block;
            font-size: 1.6rem;
            line-height: 1;
            margin-bottom: 0.35rem;
        }
        .detail-layout {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.95fr);
        }
        .detail-stack {
            display: grid;
            gap: 1rem;
        }
        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .info-grid {
            display: grid;
            gap: 0.85rem;
        }
        .info-item {
            padding: 0.9rem 1rem;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .event-list,
        .related-list,
        .review-list {
            display: grid;
            gap: 0.9rem;
        }
        .event-item,
        .related-item,
        .review-item {
            padding: 1rem;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: var(--surface);
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
        .gallery-grid {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .gallery-grid img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        @media (max-width: 980px) {
            .listing-detail-hero,
            .detail-layout {
                grid-template-columns: 1fr;
            }
            .detail-stat-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
        @media (max-width: 640px) {
            .detail-top {
                flex-direction: column;
                align-items: flex-start;
            }
            .detail-stat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $coverImage = $listing->featured_image ?: $listing->photos->first()?->image_path;
    @endphp
    <section class="listing-detail-hero">
        <div class="detail-panel">
            <span class="eyebrow">Business profile</span>
            <div class="detail-top" style="margin-top:0.75rem;">
                <div>
                    <div class="detail-meta">
                        <span>{{ $listing->city ?: 'Location pending' }}{{ $listing->region ? ', '.$listing->region : '' }}</span>
                        @if ($listing->is_featured)
                            <span class="featured-pill">Featured listing</span>
                        @endif
                    </div>
                    <h1 style="font-size:clamp(2rem, 3.5vw, 3rem); line-height:1.08; margin:0.5rem 0 0.75rem;">{{ $listing->title }}</h1>
                    <p class="section-subtitle" style="max-width:48rem;">
                        {{ $listing->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $listing->description), 220) }}
                    </p>
                    <div style="margin-top:0.9rem;">
                        @foreach ($listing->categories as $category)
                            <span class="badge">{{ $category->name }}</span>
                        @endforeach
                    </div>
                    <div class="action-row">
                        @if ($listing->phone)
                            <a class="button" href="tel:{{ preg_replace('/\s+/', '', $listing->phone) }}">Call business</a>
                        @endif
                        @if ($listing->website_url)
                            <a class="chip-link" href="{{ $listing->website_url }}" target="_blank" rel="noreferrer">Visit website</a>
                        @endif
                        @if ($listing->email)
                            <a class="chip-link" href="mailto:{{ $listing->email }}">Send email</a>
                        @endif
                    </div>
                </div>

                @if ($listing->logo_path)
                    <img class="detail-logo" src="{{ \Illuminate\Support\Facades\Storage::url($listing->logo_path) }}" alt="{{ $listing->title }} logo" loading="lazy" decoding="async">
                @endif
            </div>

            @if ($coverImage)
                <img class="detail-cover" src="{{ \Illuminate\Support\Facades\Storage::url($coverImage) }}" alt="{{ $listing->title }}" decoding="async" fetchpriority="high">
            @endif
        </div>

        <div class="detail-stat-grid">
            <div class="detail-stat">
                <strong>{{ $profileStats['reviews'] }}</strong>
                <span>Approved reviews</span>
            </div>
            <div class="detail-stat">
                <strong>{{ $profileStats['average_rating'] ? number_format((float) $profileStats['average_rating'], 1) : '-' }}</strong>
                <span>Average rating</span>
            </div>
            <div class="detail-stat">
                <strong>{{ $profileStats['events'] }}</strong>
                <span>Linked events</span>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="detail-layout">
            <div class="detail-stack">
                <article class="card">
                    <div class="section-head">
                        <div>
                            <h2>About This Business</h2>
                            <p class="section-subtitle">Everything you need to contact, visit, and learn about this business — all in one place.</p>
                        </div>
                    </div>
                    <div style="margin-top:0.75rem;">{!! nl2br(e($listing->description ?: $listing->excerpt ?: 'Business description coming soon.')) !!}</div>
                </article>

                @if ($listing->photos->isNotEmpty())
                    <article class="card">
                        <div class="section-head">
                            <div>
                                <h2>Photo Gallery</h2>
                                <p class="section-subtitle">Photos of the business, its premises, team, and work.</p>
                            </div>
                        </div>
                        <div class="gallery-grid" style="margin-top:0.9rem;">
                            @foreach ($listing->photos as $photo)
                                <div>
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->image_path) }}" alt="{{ $photo->caption ?: $listing->title }}" loading="lazy" decoding="async">
                                    @if ($photo->caption)
                                        <p class="muted" style="margin-top:0.5rem;">{{ $photo->caption }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endif

                @if ($listing->vouchers->isNotEmpty())
                    <article class="card">
                        <div class="section-head">
                            <div>
                                <h2>Vouchers</h2>
                                <p class="section-subtitle">Limited-time offers available from this business.</p>
                            </div>
                            <a href="{{ route('vouchers.index', ['listing' => $listing->slug]) }}">View all</a>
                        </div>
                        <div class="grid" style="margin-top:0.9rem;">
                            @foreach ($listing->vouchers as $voucher)
                                <div class="event-item" style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; align-items:center;">
                                    <div>
                                        <strong><a href="{{ route('vouchers.show', [$listing, $voucher]) }}">{{ $voucher->title }}</a></strong>
                                        <div class="detail-meta">
                                            <span>{{ $voucher->formattedValue() ?: 'Offer' }}</span>
                                            @if ($voucher->end_at)
                                                <span>Ends {{ $voucher->end_at->format('j M Y') }}</span>
                                            @endif
                                            <span>{{ $voucher->remainingUses() }} left</span>
                                        </div>
                                    </div>
                                    <a class="chip-link" href="{{ route('vouchers.show', [$listing, $voucher]) }}">View</a>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endif

                <article class="card">
                    <div class="section-head">
                        <div>
                            <h2>Reviews</h2>
                            <p class="section-subtitle">What customers are saying about this business.</p>
                        </div>
                    </div>
                    <div class="review-list" style="margin-top:0.9rem;">
                        @forelse ($listing->reviews as $review)
                            <div class="review-item">
                                <div class="detail-meta">
                                    <span>{{ str_repeat('*', (int) $review->rating) }}</span>
                                    <span>{{ $review->author?->name ?: $review->author_name ?: 'Guest reviewer' }}</span>
                                </div>
                                @if ($review->title)
                                    <strong style="display:block; margin-top:0.45rem;">{{ $review->title }}</strong>
                                @endif
                                <p style="margin:0.55rem 0 0;">{{ $review->body }}</p>
                                @if ($review->owner_response)
                                    <div class="empty-state" style="margin-top:0.75rem;">
                                        <strong>Business response</strong><br>
                                        {{ $review->owner_response }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="empty-state">No approved reviews yet.</div>
                        @endforelse
                    </div>
                </article>

                <article class="card">
                    <div class="section-head">
                        <div>
                            <h2>Upcoming Events</h2>
                            <p class="section-subtitle">Events hosted or organised by this business.</p>
                        </div>
                        <a href="{{ route('events.index') }}">Browse all events</a>
                    </div>
                    <div class="event-list" style="margin-top:0.9rem;">
                        @forelse ($listing->events as $event)
                            <div class="event-item">
                                <h3 style="margin:0 0 0.4rem;"><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></h3>
                                <div class="detail-meta">
                                    <span>{{ optional($event->start_at)->format('j M Y g:i A') ?: 'Date pending' }}</span>
                                    @if ($event->venue_name)
                                        <span>{{ $event->venue_name }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="empty-state">No events linked to this listing yet.</div>
                        @endforelse
                    </div>
                </article>
            </div>

            <aside class="detail-stack">
                <article class="card">
                    <h3>Contact and Location</h3>
                    <div class="info-grid" style="margin-top:0.9rem;">
                        <div class="info-item"><strong>Email</strong><br>
                            @if($listing->email)
                                <a href="mailto:{{ $listing->email }}" class="text-primary hover:underline" style="color:var(--primary-dark);">{{ $listing->email }}</a>
                            @else
                                Not available yet
                            @endif
                        </div>
                        <div class="info-item"><strong>Phone</strong><br>
                            @if($listing->phone)
                                <a href="tel:{{ preg_replace('/\s+/', '', $listing->phone) }}" class="text-primary hover:underline" style="color:var(--primary-dark);">{{ $listing->phone }}</a>
                            @else
                                Not available yet
                            @endif
                        </div>
                        <div class="info-item"><strong>Address</strong><br>{{ $listing->address_line ?: 'Not available yet' }}</div>
                        <div class="info-item"><strong>City / Region</strong><br>{{ $listing->city ?: 'Unknown' }}{{ $listing->region ? ', '.$listing->region : '' }}</div>
                        <div class="info-item"><strong>Website</strong><br>
                            @if($listing->website_url)
                                <a href="{{ $listing->website_url }}" target="_blank" rel="noreferrer" class="text-primary hover:underline" style="color:var(--primary-dark); word-break:break-all;">{{ $listing->website_url }}</a>
                            @else
                                Not available yet
                            @endif
                        </div>
                        <div class="info-item"><strong>Coordinates</strong><br>
                            @if ($listing->latitude && $listing->longitude)
                                {{ $listing->latitude }}, {{ $listing->longitude }}
                            @else
                                Not available yet
                            @endif
                        </div>
                    </div>
                </article>

                <article class="card">
                    <h3>Map &amp; Directions</h3>
                    @if ($listing->latitude && $listing->longitude)
                        <div style="margin-top:0.9rem; border-radius:14px; overflow:hidden;">
                            <x-map-embed
                                map-id="listing-map"
                                :lat="(float) $listing->latitude"
                                :lng="(float) $listing->longitude"
                                :label="$listing->title"
                                height="260px"
                            />
                        </div>
                        <a class="chip-link"
                           style="margin-top:0.75rem; display:inline-flex;"
                           href="https://www.openstreetmap.org/directions?from=&to={{ $listing->latitude }},{{ $listing->longitude }}"
                           target="_blank" rel="noreferrer">
                            Get directions &rarr;
                        </a>
                    @else
                        <div class="map-box" style="margin-top:0.9rem;">
                            <div>
                                <strong>Location not yet mapped</strong>
                                <p style="margin:0.5rem 0 0;">Use the address details above to find this business on a map.</p>
                            </div>
                        </div>
                    @endif
                </article>

                <article class="promo-zone">
                    <span class="eyebrow">Grow your visibility</span>
                    <h3 style="margin:0.45rem 0 0.6rem;">Upgrade with banner ads, homepage exposure, and targeted push campaigns.</h3>
                    <p class="muted">Reach more local customers across the Eastern Freestate with premium ad placement on top of your directory profile.</p>
                    <div class="action-row">
                        <a class="button" href="{{ route('advertise.index') }}">Advertise</a>
                        <a class="chip-link" href="{{ route('checkout.index') }}">View packages</a>
                    </div>
                </article>

                @if (isset($activeCampaign) && $activeCampaign)
                    <x-ad-campaign-card :campaign="$activeCampaign" />
                @endif

                <article class="card">
                    <h3>Related Listings</h3>
                    <div class="related-list" style="margin-top:0.9rem;">
                        @forelse ($relatedListings as $relatedListing)
                            <div class="related-item">
                                <div class="detail-meta">
                                    <span>{{ $relatedListing->city ?: 'Location pending' }}</span>
                                    @if ($relatedListing->is_featured)
                                        <span class="featured-pill">Featured</span>
                                    @endif
                                </div>
                                <h4 style="margin:0.45rem 0;"><a href="{{ route('directory.show', $relatedListing) }}">{{ $relatedListing->title }}</a></h4>
                                <p style="margin:0;">{{ \Illuminate\Support\Str::limit($relatedListing->excerpt ?: strip_tags((string) $relatedListing->description), 110) }}</p>
                            </div>
                        @empty
                            <div class="empty-state">No related listings found yet.</div>
                        @endforelse
                    </div>
                </article>
            </aside>
        </div>
    </section>
@endsection
