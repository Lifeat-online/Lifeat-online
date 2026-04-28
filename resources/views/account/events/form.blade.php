@extends('layouts.public')

@section('title', $pageTitle.' | '.$listing->title)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>{{ $pageTitle }}</h2>
                <p class="muted">Create and update business-linked events from your owner workspace. Published events require the linked listing to have an active business package.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.events.index', $listing) }}">Back to events</a>
            </div>
        </div>
    </section>

    <section class="section">
        @if ($event->exists)
            <article class="card" style="margin-bottom:1rem;">
                <h3>Event Commerce Status</h3>
                <p><strong>Visibility:</strong>
                    @if ($event->isPubliclyVisible())
                        Publicly visible
                    @elseif ($event->status === 'published')
                        Published status set, waiting on active event entitlement
                    @else
                        Draft / not public
                    @endif
                </p>
                <p><strong>Event package:</strong>
                    @if ($event->activeSubscription)
                        {{ $event->activeSubscription->package?->name ?: 'Package' }} · {{ ucfirst($event->activeSubscription->status) }} · Ends {{ optional($event->activeSubscription->ends_at)->format('j M Y') ?: '-' }}
                    @else
                        No active event package yet
                    @endif
                </p>
                <p><strong>Latest order:</strong> {{ $latestOrder?->order_number ?: '-' }}</p>
                <p><strong>Latest invoice:</strong> {{ $latestInvoice?->invoice_number ?: '-' }}</p>
                <p><strong>Latest payment:</strong> {{ ucfirst($latestPayment?->status ?: 'none') }}</p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    @if ($event->activeSubscription)
                        <a class="button-link" href="{{ route('checkout.subscriptions.renew', $event->activeSubscription) }}">Renew event package</a>
                    @endif
                    @if ($latestOrder)
                        <a class="button-link" href="{{ route('checkout.show', $latestOrder) }}">Open latest order</a>
                    @endif
                    @if ($latestInvoice)
                        <a class="button-link" href="{{ route('account.invoices.show', $latestInvoice) }}">Open latest invoice</a>
                    @endif
                </div>
            </article>
        @endif

        <article class="card">
            @if ($errors->any())
                <div class="empty-state" style="margin-bottom:1rem; color:#b91c1c;">
                    {{ implode(' ', $errors->all()) }}
                </div>
            @endif

            @if (session('status'))
                <div class="empty-state" style="margin-bottom:1rem; color:#166534;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" class="form-grid">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <div>
                    <label for="title">Title</label>
                    <input id="title" name="title" value="{{ old('title', $event->title) }}">
                </div>
                <div>
                    <label for="venue_name">Venue</label>
                    <input id="venue_name" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}">
                </div>
                <div>
                    <label for="city">City</label>
                    <input id="city" name="city" value="{{ old('city', $event->city) }}">
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" @selected(old('status', $event->status ?: 'draft') === 'draft')>Draft</option>
                        <option value="published" @selected(old('status', $event->status) === 'published')>Published</option>
                    </select>
                </div>
                <div>
                    <label for="start_at">Start</label>
                    <input id="start_at" name="start_at" type="datetime-local" value="{{ old('start_at', optional($event->start_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="end_at">End</label>
                    <input id="end_at" name="end_at" type="datetime-local" value="{{ old('end_at', optional($event->end_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="website_url">Website</label>
                    <input id="website_url" name="website_url" value="{{ old('website_url', $event->website_url) }}">
                </div>
                <div>
                    <label for="featured_image_upload">Featured image</label>
                    <input id="featured_image_upload" name="featured_image_upload" type="file" accept="image/*">
                    @if ($event->featured_image)
                        <label style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                            <input type="checkbox" name="remove_featured_image" value="1">
                            <span>Remove current featured image</span>
                        </label>
                    @endif
                </div>
                <div>
                    <label for="category_ids">Categories</label>
                    <select id="category_ids" name="category_ids[]" multiple size="5">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(in_array($category->id, old('category_ids', $selectedCategoryIds), true))>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="country">Country</label>
                    <input id="country" name="country" value="{{ old('country', $event->country) }}">
                </div>
                <div>
                    <label for="address_line">Address</label>
                    <input id="address_line" name="address_line" value="{{ old('address_line', $event->address_line) }}">
                </div>
                <div>
                    <label for="region">Region</label>
                    <input id="region" name="region" value="{{ old('region', $event->region) }}">
                </div>
                <div>
                    <label for="postal_code">Postal code</label>
                    <input id="postal_code" name="postal_code" value="{{ old('postal_code', $event->postal_code) }}">
                </div>

                <div style="grid-column: 1 / -1; margin: 1rem 0;">
                    <label>Map Location (Optional, falls back to business location if empty)</label>
                    <x-location-picker
                        :lat="old('latitude', $event->latitude)"
                        :lng="old('longitude', $event->longitude)"
                    />
                </div>

                <div style="grid-column:1 / -1;">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="3">{{ old('excerpt', $event->excerpt) }}</textarea>
                </div>
                <div style="grid-column:1 / -1;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="8">{{ old('description', $event->description) }}</textarea>
                </div>

                <label style="display:flex; gap:0.5rem; align-items:center;">
                    <input type="checkbox" name="is_all_day" value="1" @checked(old('is_all_day', $event->is_all_day))>
                    <span>All day event</span>
                </label>

                <div style="grid-column:1 / -1; display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button class="button" type="submit">Save event</button>
                    @if ($event->exists)
                        <a class="button-link" href="{{ route('checkout.index', ['event' => $event->slug]) }}">Buy event package</a>
                    @endif
                    <a class="button-link" href="{{ route('account.listings.events.index', $listing) }}">Cancel</a>
                </div>
            </form>
        </article>
    </section>
@endsection
