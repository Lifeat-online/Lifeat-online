@extends('layouts.public')

@section('title', 'Taxi / Delivery | Life Platform')

@push('styles')
    <style>
        .transport-hero { display:grid; gap:1rem; grid-template-columns:minmax(0, 1.25fr) minmax(280px, 0.75fr); align-items:stretch; }
        .transport-hero-panel { border-radius:24px; padding:1.75rem; border:1px solid rgb(var(--border-rgb) / 0.9); background:linear-gradient(135deg, rgb(var(--brand-rgb) / 0.10), rgb(var(--accent-rgb) / 0.08)), rgb(var(--surface-rgb) / 0.94); box-shadow:var(--shadow-soft); }
        .transport-grid { display:grid; gap:0.85rem; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); }
        .transport-tile { border:1px solid rgb(var(--border-rgb) / 0.9); border-radius:14px; padding:1rem; background:rgb(var(--surface-rgb) / 0.82); }
        .transport-actions { display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1rem; }
        .transport-stack { display:grid; gap:0.75rem; margin-top:1rem; }
        .transport-stat { display:grid; gap:0.25rem; padding:0.9rem; border-radius:14px; border:1px solid rgb(var(--border-rgb) / 0.9); background:rgb(var(--surface-rgb) / 0.76); }
        .transport-live { display:grid; gap:1rem; grid-template-columns:minmax(0, 1.35fr) minmax(280px, 0.65fr); align-items:stretch; }
        .transport-live-map { min-height:360px; border-radius:18px; overflow:hidden; border:1px solid rgb(var(--border-rgb) / 0.9); background:rgb(var(--surface-rgb) / 0.78); }
        .transport-live-list { display:grid; gap:0.65rem; align-content:start; }
        .transport-driver-row { display:flex; justify-content:space-between; gap:0.75rem; align-items:flex-start; padding:0.85rem; border-radius:14px; border:1px solid rgb(var(--border-rgb) / 0.82); background:rgb(var(--surface-rgb) / 0.76); text-align:left; }
        .transport-driver-row strong { display:block; }
        .transport-driver-meta { display:block; margin-top:0.2rem; font-size:0.85rem; color:var(--muted); }
        .transport-status-pill { display:inline-flex; align-items:center; gap:0.35rem; border-radius:999px; padding:0.25rem 0.65rem; font-size:0.78rem; font-weight:800; white-space:nowrap; }
        .transport-status-pill::before { content:""; width:0.5rem; height:0.5rem; border-radius:999px; background:currentColor; }
        .transport-status-pill.available { background:rgb(22 163 74 / 0.13); color:#16a34a; }
        .transport-status-pill.busy { background:rgb(249 115 22 / 0.15); color:#f97316; }
        .transport-live-summary { display:grid; gap:0.75rem; grid-template-columns:repeat(2, 1fr); margin-top:1rem; }
        @media (max-width: 920px) {
            .transport-hero,
            .transport-live { grid-template-columns:1fr; }
        }
        @media (max-width: 560px) {
            .transport-live-summary { grid-template-columns:1fr; }
            .transport-driver-row { flex-direction:column; }
        }
    </style>
@endpush

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <span class="eyebrow">Online drivers</span>
                <h2 class="h2-tight">Live driver availability</h2>
                <p class="muted mb-0">Available drivers are ready for dispatch. Occupied drivers are online but currently handling accepted transport work.</p>
            </div>
        </div>

        <div class="transport-live">
            <div class="transport-live-map">
                @if (count($driverMapMarkers) > 0)
                    <x-map-embed map-id="transport-driver-map" :markers="$driverMapMarkers" height="360px" />
                @else
                    <div class="empty-state" style="min-height:360px; display:grid; place-items:center; border:0; border-radius:18px;">
                        No online drivers have shared a live GPS position yet.
                    </div>
                @endif
            </div>

            <aside class="card">
                <span class="eyebrow">Dispatch status</span>
                <div class="transport-live-summary">
                    <div class="transport-stat">
                        <strong>{{ $availableDriverCount }}</strong>
                        <span class="muted">Available</span>
                    </div>
                    <div class="transport-stat">
                        <strong>{{ $occupiedDriverCount }}</strong>
                        <span class="muted">Occupied</span>
                    </div>
                </div>

                <div class="transport-live-list" style="margin-top:1rem;">
                    @forelse ($activeDriverSessions as $session)
                        @php
                            $isBusy = $session->status === \App\Models\TransportDutySession::STATUS_BUSY;
                            $vehicleLabel = $session->vehicle?->name ?: ucfirst((string) ($session->vehicle?->vehicle_type ?: 'vehicle'));
                        @endphp
                        <div class="transport-driver-row">
                            <span>
                                <strong>{{ $session->driver?->user?->name ?: 'Online driver' }}</strong>
                                <span class="transport-driver-meta">{{ $vehicleLabel }}</span>
                                <span class="transport-driver-meta">
                                    @if ($session->last_latitude && $session->last_longitude)
                                        Location live{{ $session->last_seen_at ? ' - '.$session->last_seen_at->diffForHumans() : '' }}
                                    @else
                                        Waiting for GPS position
                                    @endif
                                </span>
                            </span>
                            <span class="transport-status-pill {{ $isBusy ? 'busy' : 'available' }}">
                                {{ $isBusy ? 'Occupied' : 'Available' }}
                            </span>
                        </div>
                    @empty
                        <div class="empty-state" style="padding:1rem;">No drivers are online right now.</div>
                    @endforelse
                </div>
            </aside>
        </div>
    </section>

    <section class="section transport-hero">
        <article class="transport-hero-panel">
            <span class="badge">Taxi / Delivery</span>
            <h2 class="h2-tight">Move people, parcels, groceries, tools, and small loads through local transport work.</h2>
            <p class="muted mb-0">The platform supports bicycles, scooters, cars, bakkies, LDVs, and larger vehicles so drivers can earn with the transport they already have.</p>
            <div class="transport-actions">
                <a class="button" href="{{ route('transport.requests.create') }}">Request taxi or delivery</a>
                <a class="button-link" href="{{ route('transport.driver.duty') }}">Driver clock-in</a>
                @auth
                    @if (auth()->user()->hasRole('transport_manager', 'admin', 'dev'))
                        <a class="button-link" href="{{ route('transport.manager.dashboard') }}">Manager tools</a>
                    @endif
                @endauth
            </div>
        </article>
        <aside class="card">
            <span class="eyebrow">Live dispatch</span>
            <div class="transport-stack">
                <div class="transport-stat">
                    <strong>Clients can still schedule when nobody is online.</strong>
                    <span class="muted">Immediate requests dispatch to available matching drivers; scheduled jobs are kept for later fulfilment.</span>
                </div>
                <div class="transport-stat">
                    <strong>Websockets only wake up on active live pages.</strong>
                    <span class="muted">Driver and client tracking stays real time without opening sockets across the whole site.</span>
                </div>
            </div>
        </aside>
    </section>

    <section class="section">
        <div class="transport-grid">
            <article class="transport-tile">
                <strong>Small parcels</strong>
                <p class="muted mb-0">Bicycles and scooters can handle lightweight collections at lower fees.</p>
            </article>
            <article class="transport-tile">
                <strong>Rides</strong>
                <p class="muted mb-0">Cars can price per kilometre, or per kilometre plus passenger count.</p>
            </article>
            <article class="transport-tile">
                <strong>Bigger deliveries</strong>
                <p class="muted mb-0">Bakkies, LDVs, vans, and trucks can be configured for heavier items.</p>
            </article>
            <article class="transport-tile">
                <strong>Payments</strong>
                <p class="muted mb-0">Cash, driver card machine, and PayFast online payment options are supported per vehicle.</p>
            </article>
            <article class="transport-tile">
                <strong>Platform fee</strong>
                <p class="muted mb-0">The platform tracks its 10% fee on vehicle earnings for transaction management.</p>
            </article>
            <article class="transport-tile">
                <strong>Safety</strong>
                <p class="muted mb-0">Driver and client panic-button configuration is part of the transport safety roadmap.</p>
            </article>
        </div>
    </section>
@endsection
