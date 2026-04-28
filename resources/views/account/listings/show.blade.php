@extends('layouts.public')

@section('title', $listing->title.' | My Listings')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>{{ $listing->title }}</h2>
                <p class="muted">{{ ucfirst($listing->status) }}{{ $listing->city ? ' · '.$listing->city : '' }}</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.index') }}">Back to listings</a>
                <a class="button-link" href="{{ route('account.listings.edit', $listing) }}">Edit profile</a>
                @if ($listing->activeSubscription)
                    <a class="button" href="{{ route('checkout.subscriptions.renew', $listing->activeSubscription) }}">Renew subscription</a>
                @else
                    <a class="button" href="{{ route('checkout.index', ['listing' => $listing->slug]) }}">Continue package checkout</a>
                @endif
            </div>
        </div>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>Listing Status</h3>
                <p><strong>Source:</strong> {{ ucfirst(str_replace('_', ' ', $listing->source_channel ?: 'unknown')) }}</p>
                <p><strong>Visibility:</strong>
                    @if ($listing->hasActiveBusinessEntitlement())
                        Publicly visible
                    @elseif ($listing->status === 'published')
                        Published status set, awaiting active entitlement
                    @else
                        Draft / onboarding
                    @endif
                </p>
                <p><strong>Category count:</strong> {{ $listing->categories->count() }}</p>
                <p><strong>Package expiry:</strong> {{ optional($listing->package_expires_at)->format('j M Y H:i') ?: '-' }}</p>
            </article>

            <article class="card">
                <h3>Latest Commerce Step</h3>
                <p><strong>Latest order:</strong> {{ $latestOrder?->order_number ?: '-' }}</p>
                <p><strong>Latest invoice:</strong> {{ $latestInvoice?->invoice_number ?: '-' }}</p>
                <p><strong>Latest payment:</strong> {{ ucfirst($latestPayment?->status ?: 'none') }}</p>
                @if ($latestOrder)
                    <p><a class="button-link" href="{{ route('checkout.show', $latestOrder) }}">Open latest order</a></p>
                @endif
                @if ($latestInvoice)
                    <p><a class="button-link" href="{{ route('account.invoices.show', $latestInvoice) }}">Open latest invoice</a></p>
                @endif
            </article>
        </div>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>Push Campaigns</h3>
                <p class="muted">Compose premium push notifications tied to this listing and manage their package handoff.</p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    <a class="button" href="{{ route('account.listings.push-campaigns.create', $listing) }}">Create push campaign</a>
                    <a class="button-link" href="{{ route('account.listings.push-campaigns.index', $listing) }}">View all push campaigns</a>
                </div>
                @forelse ($listing->pushCampaigns->sortByDesc('created_at')->take(3) as $campaign)
                    <div style="padding-top:1rem; margin-top:1rem; border-top:1px solid rgba(15, 23, 42, 0.08);">
                        <strong>{{ $campaign->title }}</strong>
                        <div class="muted">{{ ucfirst($campaign->status) }} · {{ optional($campaign->schedule_at)->format('j M Y g:i A') ?: 'Schedule pending' }}</div>
                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.5rem;">
                            <a class="button-link" href="{{ route('account.listings.push-campaigns.edit', [$listing, $campaign]) }}">Edit push</a>
                            <a class="button-link" href="{{ route('checkout.index', ['push_campaign' => $campaign->slug]) }}">Buy push package</a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state" style="margin-top:1rem;">No push campaigns for this listing yet.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Advert Campaigns</h3>
                <p class="muted">Create and track paid promotion campaigns tied to this listing.</p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    <a class="button" href="{{ route('account.listings.ad-campaigns.create', $listing) }}">Create advert campaign</a>
                    <a class="button-link" href="{{ route('account.listings.ad-campaigns.index', $listing) }}">View all campaigns</a>
                </div>
                @forelse ($listing->adCampaigns->sortByDesc('created_at')->take(3) as $campaign)
                    <div style="padding-top:1rem; margin-top:1rem; border-top:1px solid rgba(15, 23, 42, 0.08);">
                        <strong>{{ $campaign->title }}</strong>
                        <div class="muted">{{ ucfirst($campaign->status) }} · {{ optional($campaign->start_at)->format('j M Y') ?: 'Start pending' }}</div>
                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.5rem;">
                            <a class="button-link" href="{{ route('account.listings.ad-campaigns.edit', [$listing, $campaign]) }}">Edit campaign</a>
                            <a class="button-link" href="{{ route('checkout.index', ['campaign' => $campaign->slug]) }}">Buy advert package</a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state" style="margin-top:1rem;">No advert campaigns for this listing yet.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Events</h3>
                <p class="muted">Create and manage events tied to this business listing.</p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    <a class="button" href="{{ route('account.listings.events.create', $listing) }}">Create event</a>
                    <a class="button-link" href="{{ route('account.listings.events.index', $listing) }}">View all events</a>
                </div>
                @forelse ($listing->events->sortByDesc('start_at')->take(3) as $event)
                    <div style="padding-top:1rem; margin-top:1rem; border-top:1px solid rgba(15, 23, 42, 0.08);">
                        <strong>{{ $event->title }}</strong>
                        <div class="muted">{{ ucfirst($event->status) }} · {{ optional($event->start_at)->format('j M Y g:i A') ?: 'Date pending' }}</div>
                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.5rem;">
                            <a class="button-link" href="{{ route('account.listings.events.edit', [$listing, $event]) }}">Edit event</a>
                            <a class="button-link" href="{{ route('checkout.index', ['event' => $event->slug]) }}">Buy event package</a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state" style="margin-top:1rem;">No events for this listing yet.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Photo Gallery</h3>
                <form method="post" action="{{ route('account.listings.photos.store', $listing) }}" enctype="multipart/form-data" style="margin-top:1rem;">
                    @csrf
                    <label for="photo_upload">Upload photo</label>
                    <input id="photo_upload" name="photo_upload" type="file" accept="image/*">
                    <label for="caption" style="margin-top:0.75rem;">Caption</label>
                    <input id="caption" name="caption" value="{{ old('caption') }}">
                    <button class="button" type="submit" style="margin-top:0.75rem;">Add photo</button>
                </form>

                @forelse ($listing->photos as $photo)
                    <div style="padding-top:1rem; margin-top:1rem; border-top:1px solid rgba(15, 23, 42, 0.08);">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->image_path) }}" alt="{{ $photo->caption ?: $listing->title }}" style="width:100%; max-height:220px; object-fit:cover; border-radius:16px;">
                        @if ($loop->first)
                            <p class="muted" style="margin-top:0.5rem;"><strong>Primary photo</strong> · Leads the public gallery and acts as the cover fallback.</p>
                        @endif
                        @if ($photo->caption)
                            <p class="muted" style="margin-top:0.5rem;">{{ $photo->caption }}</p>
                        @endif
                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                            @unless ($loop->first)
                                <form method="post" action="{{ route('account.listings.photos.primary', [$listing, $photo]) }}">
                                    @csrf
                                    <button class="button-link" type="submit">Make primary</button>
                                </form>
                            @endunless
                            <form method="post" action="{{ route('account.listings.photos.destroy', [$listing, $photo]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="button-link" type="submit">Remove photo</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state" style="margin-top:1rem;">No gallery photos yet.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Customer Reviews</h3>
                @forelse ($listing->reviews->where('status', 'approved')->sortByDesc('id') as $review)
                    <div style="padding:1rem 0; border-bottom:1px solid rgba(15, 23, 42, 0.08);">
                        <p><strong>{{ $review->title ?: 'Review' }}</strong></p>
                        <p class="muted">{{ str_repeat('*', (int) $review->rating) }} · {{ $review->author?->name ?: $review->author_name ?: 'Guest reviewer' }}</p>
                        <p>{{ $review->body }}</p>

                        @if ($review->owner_response)
                            <div class="empty-state" style="margin-top:0.75rem;">
                                <strong>Your response</strong><br>
                                {{ $review->owner_response }}
                            </div>
                        @endif

                        <form method="post" action="{{ route('account.listings.reviews.respond', [$listing, $review]) }}" style="margin-top:0.75rem;">
                            @csrf
                            <label for="owner_response_{{ $review->id }}">Owner response</label>
                            <textarea id="owner_response_{{ $review->id }}" name="owner_response" rows="4">{{ old('owner_response', $review->owner_response) }}</textarea>
                            <button class="button" type="submit" style="margin-top:0.75rem;">{{ $review->owner_response ? 'Update response' : 'Post response' }}</button>
                        </form>
                    </div>
                @empty
                    <div class="empty-state">No approved customer reviews yet.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Subscription History</h3>
                @forelse ($listing->subscriptions->sortByDesc('id') as $subscription)
                    <p>
                        <strong>{{ $subscription->package?->name ?: 'Package' }}</strong><br>
                        <span class="muted">{{ ucfirst($subscription->status) }} · Ends {{ optional($subscription->ends_at)->format('j M Y') ?: '-' }}</span>
                    </p>
                @empty
                    <div class="empty-state">No subscriptions yet. Start checkout to attach a package.</div>
                @endforelse
            </article>

            <article class="card">
                <h3>Starter Details</h3>
                <p><strong>Slug:</strong> {{ $listing->slug }}</p>
                <p><strong>Address:</strong> {{ collect([$listing->address_line, $listing->city, $listing->region, $listing->country])->filter()->join(', ') ?: '-' }}</p>
                <p><strong>Website:</strong> {{ $listing->website_url ?: '-' }}</p>
                <p><strong>Email:</strong> {{ $listing->email ?: '-' }}</p>
                <p><strong>Phone:</strong> {{ $listing->phone ?: '-' }}</p>
                <p class="muted">
                    @if ($listing->source_channel === 'self_service')
                        This listing started in the self-service flow and can continue through owner checkout and renewal steps.
                    @else
                        This listing started in a staff-assisted flow. Finance and package history are still visible here for owner follow-up.
                    @endif
                </p>
            </article>
        </div>
    </section>
@endsection
