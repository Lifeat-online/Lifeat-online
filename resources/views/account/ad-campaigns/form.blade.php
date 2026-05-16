@extends('layouts.public')

@section('title', $pageTitle.' | '.$listing->title)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>{{ $pageTitle }}</h2>
                <p class="muted">Manage advert creative, destination, schedule, and package handoff from the linked business listing.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.ad-campaigns.index', $listing) }}">Back to advert campaigns</a>
            </div>
        </div>
    </section>

    <section class="section">
        @if ($campaign->exists)
            <article class="card" style="margin-bottom:1rem;">
                <h3>Advert Commerce Status</h3>
                <p><strong>Operational state:</strong>
                    @if ($campaign->isOperational())
                        Operational
                    @elseif ($campaign->status === 'active')
                        Active status set, waiting on valid advert entitlement
                    @else
                        Draft / ready
                    @endif
                </p>
                <p><strong>Advert package:</strong>
                    @if ($campaign->activeSubscription)
                        {{ $campaign->activeSubscription->package?->name ?: 'Package' }} · {{ ucfirst($campaign->activeSubscription->status) }} · Ends {{ optional($campaign->activeSubscription->ends_at)->format('j M Y') ?: '-' }}
                    @else
                        No active advert package yet
                    @endif
                </p>
                <p><strong>Latest order:</strong> {{ $latestOrder?->order_number ?: '-' }}</p>
                <p><strong>Latest invoice:</strong> {{ $latestInvoice?->invoice_number ?: '-' }}</p>
                <p><strong>Latest payment:</strong> {{ ucfirst($latestPayment?->status ?: 'none') }}</p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    @if ($campaign->activeSubscription)
                        <a class="button-link" href="{{ route('checkout.subscriptions.renew', $campaign->activeSubscription) }}">Renew advert package</a>
                    @endif
                    <a class="button-link" href="{{ route('checkout.index', ['campaign' => $campaign->slug]) }}">Buy advert package</a>
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
                    <label for="title">Campaign title</label>
                    <input id="title" name="title" value="{{ old('title', $campaign->title) }}">
                </div>
                <div>
                    <label for="headline">Headline</label>
                    <input id="headline" name="headline" value="{{ old('headline', $campaign->headline) }}">
                </div>
                <div>
                    <label for="destination_url">Destination URL</label>
                    <input id="destination_url" name="destination_url" value="{{ old('destination_url', $campaign->destination_url) }}">
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach (['draft', 'ready', 'active'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $campaign->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="placement">Placement</label>
                    <select id="placement" name="placement">
                        @foreach ([
                            'banner' => 'Section banner',
                            'sitewide_banner' => 'Sitewide banner',
                            'in_article_intro' => 'After article intro',
                            'in_article_mid' => 'Between article sections',
                            'in_article_end' => 'After article',
                            'popup' => 'Promotional pop-up',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('placement', $campaign->placement ?: 'banner') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="event_id">Linked event</label>
                    <select id="event_id" name="event_id">
                        <option value="">No linked event</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected((string) old('event_id', $campaign->event_id) === (string) $event->id)>{{ $event->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="creative_image_upload">Creative image</label>
                    <input id="creative_image_upload" name="creative_image_upload" type="file" accept="image/*">
                    @if ($campaign->creative_image)
                        <label style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                            <input type="checkbox" name="remove_creative_image" value="1">
                            <span>Remove current creative image</span>
                        </label>
                    @endif
                </div>
                <div>
                    <label for="start_at">Start date</label>
                    <input id="start_at" name="start_at" type="datetime-local" value="{{ old('start_at', optional($campaign->start_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="end_at">End date</label>
                    <input id="end_at" name="end_at" type="datetime-local" value="{{ old('end_at', optional($campaign->end_at)->format('Y-m-d\TH:i')) }}">
                </div>

                <div style="grid-column:1 / -1;">
                    <label for="body">Creative copy</label>
                    <textarea id="body" name="body" rows="6">{{ old('body', $campaign->body) }}</textarea>
                </div>

                <div style="grid-column:1 / -1; display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button class="button" type="submit">Save advert campaign</button>
                    @if ($campaign->exists)
                        <a class="button-link" href="{{ route('checkout.index', ['campaign' => $campaign->slug]) }}">Buy advert package</a>
                    @endif
                    <a class="button-link" href="{{ route('account.listings.ad-campaigns.index', $listing) }}">Cancel</a>
                </div>
            </form>
        </article>
    </section>
@endsection
