@extends('layouts.public')

@section('title', $pageTitle.' | '.$listing->title)

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>{{ $pageTitle }}</h2>
                <p class="muted">Compose a business-linked push notification, define a basic audience, and hand off package purchase through the existing checkout flow.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.push-campaigns.index', $listing) }}">Back to push campaigns</a>
            </div>
        </div>
    </section>

    <section class="section">
        @if ($campaign->exists)
            <article class="card" style="margin-bottom:1rem;">
                <h3>Push Commerce Status</h3>
                <p><strong>Operational state:</strong>
                    @if ($campaign->sent_at)
                        Delivered
                    @elseif ($campaign->isOperational())
                        Operational
                    @elseif (in_array($campaign->status, ['active', 'scheduled'], true))
                        Status set, waiting on valid push entitlement
                    @else
                        Draft / ready
                    @endif
                </p>
                <p><strong>Push package:</strong>
                    @if ($campaign->activeSubscription)
                        {{ $campaign->activeSubscription->package?->name ?: 'Package' }} · {{ ucfirst($campaign->activeSubscription->status) }} · Ends {{ optional($campaign->activeSubscription->ends_at)->format('j M Y') ?: '-' }}
                    @else
                        No active push package yet
                    @endif
                </p>
                <p><strong>Latest order:</strong> {{ $latestOrder?->order_number ?: '-' }}</p>
                <p><strong>Latest invoice:</strong> {{ $latestInvoice?->invoice_number ?: '-' }}</p>
                <p><strong>Latest payment:</strong> {{ ucfirst($latestPayment?->status ?: 'none') }}</p>
                <p><strong>Audience:</strong> {{ $campaign->audienceSummary() }}</p>
                <p><strong>Last dispatched:</strong> {{ optional($campaign->sent_at)->format('j M Y g:i A') ?: 'Not yet sent' }}</p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                    @if ($campaign->activeSubscription)
                        <a class="button-link" href="{{ route('checkout.subscriptions.renew', $campaign->activeSubscription) }}">Renew push package</a>
                    @endif
                    <a class="button-link" href="{{ route('checkout.index', ['push_campaign' => $campaign->slug]) }}">Buy push package</a>
                    @if ($campaign->isOperational() && (! $campaign->schedule_at || $campaign->schedule_at->isPast()))
                        <form method="post" action="{{ route('account.listings.push-campaigns.dispatch', [$listing, $campaign]) }}">
                            @csrf
                            <button class="button-link" type="submit">Send now</button>
                        </form>
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

            <form method="post" action="{{ $formAction }}" class="form-grid">
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
                    <label for="schedule_at">Schedule</label>
                    <input id="schedule_at" name="schedule_at" type="datetime-local" value="{{ old('schedule_at', optional($campaign->schedule_at)->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach (['draft', 'ready', 'scheduled', 'active'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $campaign->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>
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
                    <label for="audience_scope">Audience scope</label>
                    <select id="audience_scope" name="audience_scope">
                        @foreach (['listing_city' => 'Listing city', 'listing_region' => 'Listing region', 'custom_radius' => 'Custom radius'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience_scope', $campaign->audience_scope ?: 'listing_city') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="target_city">Target city</label>
                    <input id="target_city" name="target_city" value="{{ old('target_city', $campaign->target_city) }}">
                </div>
                <div>
                    <label for="target_region">Target region</label>
                    <input id="target_region" name="target_region" value="{{ old('target_region', $campaign->target_region) }}">
                </div>
                <div>
                    <label for="radius_km">Radius (km)</label>
                    <input id="radius_km" name="radius_km" type="number" min="1" max="200" value="{{ old('radius_km', $campaign->radius_km) }}">
                </div>

                <div style="grid-column:1 / -1;">
                    @include('partials.ai-copy-assistant', [
                        'endpoint' => route('account.listings.ai.push-copy', $listing),
                        'mode' => 'push',
                        'heading' => 'AI Push Copy',
                        'description' => 'Draft a short campaign title, headline, and message from your listing and offer.',
                        'placeholder' => 'Example: promote a weekend special, new service, event reminder, or limited offer.',
                    ])
                </div>

                <div style="grid-column:1 / -1;">
                    <label for="message">Push message</label>
                    <textarea id="message" name="message" rows="6">{{ old('message', $campaign->message) }}</textarea>
                </div>

                <div style="grid-column:1 / -1; display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button class="button" type="submit">Save push campaign</button>
                    @if ($campaign->exists)
                        <a class="button-link" href="{{ route('checkout.index', ['push_campaign' => $campaign->slug]) }}">Buy push package</a>
                    @endif
                    <a class="button-link" href="{{ route('account.listings.push-campaigns.index', $listing) }}">Cancel</a>
                </div>
            </form>
        </article>

        @if ($campaign->exists)
            <article class="card" style="margin-top:1rem;">
                <h3>Delivery History</h3>
                @forelse ($dispatchLogs as $log)
                    <div class="empty-state" style="margin-top:0.75rem; text-align:left;">
                        <strong>{{ optional($log->sent_at)->format('j M Y g:i A') ?: 'Dispatch logged' }}</strong><br>
                        {{ ucfirst($log->status) }} via {{ ucfirst($log->channel) }} to {{ $log->recipient ?: 'saved audience' }}
                    </div>
                @empty
                    <div class="empty-state" style="margin-top:0.75rem;">No delivery logs yet.</div>
                @endforelse
            </article>
        @endif
    </section>
@endsection
