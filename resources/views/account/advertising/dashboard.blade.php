@extends('layouts.public')

@section('title', 'Advertising Dashboard | Life Platform')

@push('styles')
    <style>
        .ad-grid { display: grid; gap: 1rem; }
        .ad-top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .ad-select { min-width: 260px; }
        .ad-metrics { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .metric { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 1rem; box-shadow: var(--shadow-soft); }
        .metric strong { display:block; font-size: 1.6rem; }
        .module-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .module { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; box-shadow: var(--shadow-soft); }
        .module h4 { margin: 0; }
        .module p { margin: 0.5rem 0 0; color: var(--muted); }
        .inline-actions { display:flex; gap:0.6rem; flex-wrap:wrap; margin-top: 0.9rem; }
        .status-pill { display:inline-flex; align-items:center; gap:0.5rem; padding: 0.3rem 0.65rem; border-radius: 999px; border: 1px solid var(--border); color: var(--muted); font-size: 0.9rem; }
        .dot { width: 10px; height: 10px; border-radius: 999px; background: #94a3b8; }
        .dot.active { background: #10b981; }
        .dot.paused { background: #f59e0b; }
        .dot.draft { background: #64748b; }
        .dot.ready { background: #3b82f6; }
        .notice { border-radius: 16px; border: 1px solid var(--border); padding: 1rem; background: rgba(29, 78, 216, 0.06); }
    </style>
@endpush

@section('content')
    <section class="section">
        <div class="ad-top">
            <div>
                <span class="badge">Self-Service</span>
                <h2 class="h2-tight">Advertising Dashboard</h2>
                <p class="muted mb-0">Manage your advertising add-ons and track campaign performance.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.index') }}">Account hub</a>
                <a class="button-link" href="{{ route('checkout.index') }}">Billing & packages</a>
            </div>
        </div>
    </section>

    @if ($listings->isEmpty())
        <section class="section">
            <div class="card">
                <h3>No business registered yet</h3>
                <p class="muted">Advertising options become available after you create a business listing.</p>
                <p class="mt-10"><a class="button-link" href="{{ route('add-listing.index') }}">Start business registration</a></p>
            </div>
        </section>
    @else
        <section class="section ad-grid" id="client-ad-dash" data-initial-listing-id="{{ $listings->first()->id }}">
            <div class="card">
                <div class="ad-top">
                    <div class="ad-select">
                        <label for="listing_id">Select business</label>
                        <select id="listing_id">
                            @foreach ($listings as $listing)
                                <option value="{{ $listing->id }}">{{ $listing->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="entitlement-note" class="status-pill" style="display:none;">
                        <span class="dot paused"></span>
                        <span>Business subscription required for advertising.</span>
                    </div>
                </div>
            </div>

            <div class="ad-metrics">
                <div class="metric"><div class="muted">Listing status</div><strong id="listing-status">—</strong></div>
                <div class="metric"><div class="muted">Events</div><strong id="events-count">—</strong></div>
                <div class="metric"><div class="muted">Active advert placements</div><strong id="ads-active">—</strong></div>
                <div class="metric"><div class="muted">Active push campaigns</div><strong id="push-active">—</strong></div>
                <div class="metric"><div class="muted">Total impressions</div><strong id="ads-impressions">—</strong></div>
                <div class="metric"><div class="muted">Total clicks</div><strong id="ads-clicks">—</strong></div>
            </div>

            <div class="notice" id="loading-note">Loading your campaign data…</div>

            <div class="module-grid">
                <div class="module">
                    <h4>Business listing</h4>
                    <p id="listing-copy">Keep the business profile, contact details, photos, and subscription current.</p>
                    <div class="inline-actions" id="listing-actions"></div>
                </div>
                <div class="module">
                    <h4>Events</h4>
                    <p>Create and manage event promotions linked to this business.</p>
                    <div class="inline-actions" id="event-actions"></div>
                </div>
                <div class="module">
                    <h4>Push notification campaigns</h4>
                    <p>Schedule targeted push notifications to reach local audiences.</p>
                    <div class="inline-actions" id="push-actions"></div>
                </div>
                <div class="module">
                    <h4>Banner advertisements</h4>
                    <p>Run section banners, sitewide banners, or article placements with tracked clicks and impressions.</p>
                    <div class="inline-actions" id="ad-actions"></div>
                </div>
                <div class="module">
                    <h4>Promotional pop-ups</h4>
                    <p>Launch time-boxed pop-ups with configurable triggers and targeting.</p>
                    <div class="inline-actions" id="popup-actions"></div>
                </div>
                <div class="module">
                    <h4>Email marketing integration</h4>
                    <p>Store your provider settings for staff support and future integrations.</p>
                    <div class="inline-actions" id="email-actions"></div>
                </div>
                <div class="module">
                    <h4>Social media advertising tools</h4>
                    <p>Track your social advertising configuration and targeting preferences.</p>
                    <div class="inline-actions" id="social-actions"></div>
                </div>
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const root = document.getElementById('client-ad-dash');
            if (!root) return;

            const endpoints = {
                summary: (listingId) => @json(route('api.client.advertising.summary', ['listing' => '___'])) .replace('___', String(listingId)),
            };

            const listingSelect = document.getElementById('listing_id');
            const loadingNote = document.getElementById('loading-note');
            const entitlementNote = document.getElementById('entitlement-note');

            const setText = (id, text) => {
                const el = document.getElementById(id);
                if (el) el.textContent = text;
            };

            const setActions = (id, buttons) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.innerHTML = '';
                buttons.forEach((b) => el.appendChild(b));
            };

            const actionBtn = (href, label) => {
                const a = document.createElement('a');
                a.className = 'button-link';
                a.href = href;
                a.textContent = label;
                return a;
            };

            const actionPill = (status) => {
                const s = String(status || 'draft');
                const wrap = document.createElement('span');
                wrap.className = 'status-pill';
                const dot = document.createElement('span');
                dot.className = 'dot ' + s;
                const text = document.createElement('span');
                text.textContent = s.replaceAll('_', ' ');
                wrap.appendChild(dot);
                wrap.appendChild(text);
                return wrap;
            };

            async function load() {
                const listingId = listingSelect?.value;
                if (!listingId) return;

                if (loadingNote) loadingNote.style.display = 'block';

                const res = await fetch(endpoints.summary(listingId), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const data = await res.json().catch(() => null);
                if (!data || !data.listing) return;

                const hasEntitlement = !!data.listing.has_active_business_entitlement;
                if (entitlementNote) entitlementNote.style.display = hasEntitlement ? 'none' : 'inline-flex';

                const adCampaigns = data.ad_campaigns || [];
                const pushCampaigns = data.push_campaigns || [];
                const events = data.events || [];

                const adsActive = adCampaigns.filter((c) => c.status === 'active').length;
                const pushActive = pushCampaigns.filter((c) => c.status === 'active' || c.status === 'scheduled').length;
                const impressions = adCampaigns.reduce((acc, c) => acc + Number(c.impressions || 0), 0);
                const clicks = adCampaigns.reduce((acc, c) => acc + Number(c.clicks || 0), 0);

                setText('listing-status', String(data.listing.status || 'draft').replaceAll('_', ' '));
                setText('events-count', String(events.length));
                setText('ads-active', String(adsActive));
                setText('push-active', String(pushActive));
                setText('ads-impressions', String(impressions));
                setText('ads-clicks', String(clicks));

                const listingSlug = @json($listings->keyBy('id')->map->slug);
                const slug = listingSlug[String(listingId)];
                const listingBase = slug ? @json(url('/account/listings')) + '/' + slug : null;

                const listingCopy = document.getElementById('listing-copy');
                if (listingCopy) {
                    const area = [data.listing.city, data.listing.region].filter(Boolean).join(', ');
                    listingCopy.textContent = `${data.listing.source_channel ? data.listing.source_channel.replaceAll('_', ' ') : 'Business'} listing${area ? ' · ' + area : ''}.`;
                }

                setActions('listing-actions', [
                    listingBase ? actionBtn(listingBase, 'Open') : actionPill('draft'),
                    listingBase ? actionBtn(listingBase + '/edit', 'Edit') : actionPill('draft'),
                    hasEntitlement ? actionPill('active') : actionBtn(@json(route('checkout.index')) + '?listing=' + encodeURIComponent(slug || ''), 'Activate listing'),
                ]);

                setActions('event-actions', [
                    listingBase ? actionBtn(listingBase + '/events', 'Manage') : actionPill('draft'),
                    listingBase ? actionBtn(listingBase + '/events/create', 'Create') : actionPill('draft'),
                    events[0] ? actionPill(events[0].status) : actionPill('draft'),
                ]);

                setActions('push-actions', [
                    listingBase ? actionBtn(listingBase + '/push-campaigns', 'Manage') : actionPill('draft'),
                    pushCampaigns[0] ? actionPill(pushCampaigns[0].status) : actionPill('draft'),
                ]);

                setActions('ad-actions', [
                    listingBase ? actionBtn(listingBase + '/ad-campaigns', 'Manage') : actionPill('draft'),
                    adCampaigns[0] ? actionPill(adCampaigns[0].status) : actionPill('draft'),
                ]);

                const popupActive = adCampaigns.filter((c) => c.placement === 'popup').length;
                setActions('popup-actions', [
                    listingBase ? actionBtn(listingBase + '/ad-campaigns', 'Manage') : actionPill('draft'),
                    actionPill(popupActive ? 'active' : 'draft'),
                ]);

                setActions('email-actions', [
                    actionPill('ready'),
                    actionBtn(@json(route('legal.privacy')), 'Privacy'),
                ]);

                setActions('social-actions', [
                    actionPill('ready'),
                    actionBtn(@json(route('contact.index')), 'Request support'),
                ]);

                if (loadingNote) loadingNote.style.display = 'none';
            }

            let timer = null;
            let loading = false;
            const startPolling = () => {
                if (document.hidden) return;
                if (timer) clearInterval(timer);
                timer = setInterval(refreshIfVisible, 15000);
            };

            const stopPolling = () => {
                if (!timer) return;
                clearInterval(timer);
                timer = null;
            };

            const refreshIfVisible = async () => {
                if (document.hidden || loading) return;
                loading = true;
                try {
                    await load();
                } finally {
                    loading = false;
                }
            };

            const handleVisibilityChange = () => {
                if (document.hidden) {
                    stopPolling();
                    return;
                }

                refreshIfVisible();
                startPolling();
            };

            listingSelect?.addEventListener('change', () => { refreshIfVisible(); startPolling(); });
            document.addEventListener('visibilitychange', handleVisibilityChange);
            window.addEventListener('pagehide', stopPolling);

            refreshIfVisible();
            startPolling();
        })();
    </script>
@endpush
