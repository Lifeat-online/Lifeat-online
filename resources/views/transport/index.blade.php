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
        @media (max-width: 920px) {
            .transport-hero { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <section class="section transport-hero">
        <article class="transport-hero-panel">
            <span class="badge">Taxi / Delivery</span>
            <h2 class="h2-tight">Move people, parcels, groceries, tools, and small loads through local transport work.</h2>
            <p class="muted mb-0">The platform supports bicycles, scooters, cars, bakkies, LDVs, and larger vehicles so drivers can earn with the transport they already have.</p>
            <div class="transport-actions">
                <a class="button" href="{{ route('transport.requests.create') }}">Request taxi or delivery</a>
                <a class="button-link" href="{{ route('transport.driver.duty') }}">Driver clock-in</a>
                <a class="button-link" href="{{ route('transport.manager.dashboard') }}">Manager tools</a>
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
