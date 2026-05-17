@extends('layouts.public')

@section('title', 'Transport Request | Life Platform')

@push('styles')
    <style>
        .transport-request-shell { max-width: 1060px; margin: 0 auto; display: grid; gap: 1rem; }
        .transport-request-card { border: 1px solid rgb(var(--border-rgb) / 0.9); border-radius: 18px; padding: 1.35rem; background: rgb(var(--surface-rgb) / 0.94); box-shadow: var(--shadow-soft); }
        .transport-request-head { display: flex; gap: 1rem; align-items: flex-start; justify-content: space-between; }
        .transport-request-summary { border-radius: 14px; padding: 1rem; background: rgb(var(--muted-rgb) / 0.12); }
        .transport-notice { border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.94rem; background: rgb(var(--brand-rgb) / 0.12); color: var(--text); }
        .transport-note-warning { border: 1px solid rgb(245 158 11 / 0.35); background: rgb(245 158 11 / 0.14); }
        .transport-note-success { border: 1px solid rgb(22 163 74 / 0.35); background: rgb(22 163 74 / 0.12); }
        .transport-offer { border: 1px solid rgb(var(--border-rgb) / 0.9); border-radius: 14px; padding: 1rem; }
        .transport-stack { display: grid; gap: 1rem; }
        @media (max-width: 760px) {
            .transport-request-head { display: grid; }
        }
    </style>
@endpush

@section('content')
    @php($realtimeStatuses = [\App\Models\TransportRequest::STATUS_DISPATCHING, \App\Models\TransportRequest::STATUS_ACCEPTED, \App\Models\TransportRequest::STATUS_DRIVER_ARRIVING, \App\Models\TransportRequest::STATUS_IN_TRANSIT])

    <section class="section transport-request-shell" @if (in_array($transportRequest->status, $realtimeStatuses, true)) data-transport-realtime data-channel="transport.request.{{ $transportRequest->id }}" @endif>
        <div>
            <span class="badge">Taxi / Delivery</span>
            <h2 class="h2-tight">Transport Request {{ $transportRequest->request_number }}</h2>
        </div>

        @if (session('status'))
            <div class="transport-notice">{{ session('status') }}</div>
        @endif
        @if (in_array($transportRequest->status, $realtimeStatuses, true))
            <div data-transport-notice class="transport-notice">
                Live tracking updates are active for this request.
            </div>
        @endif

        <article class="transport-request-card">
            <div class="transport-request-head">
                <div>
                    <p class="eyebrow">{{ ucfirst(str_replace('_', ' ', $transportRequest->service_type)) }}</p>
                    <h3 data-transport-status>{{ ucfirst(str_replace('_', ' ', $transportRequest->status)) }}</h3>
                    <p class="muted">{{ $transportRequest->pickup_address }} to {{ $transportRequest->dropoff_address }}</p>
                    @if ($transportRequest->scheduled_pickup_at)
                        <p class="muted">Scheduled pickup: {{ $transportRequest->scheduled_pickup_at->format('Y-m-d H:i') }}</p>
                    @endif
                </div>
                <div class="transport-request-summary">
                    <p><span class="muted">Quote:</span> <strong>ZAR {{ number_format((float) $transportRequest->quoted_amount, 2) }}</strong></p>
                    <p><span class="muted">Platform 10%:</span> ZAR {{ number_format((float) $transportRequest->platform_fee, 2) }}</p>
                    <p><span class="muted">Driver:</span> ZAR {{ number_format((float) $transportRequest->driver_amount, 2) }}</p>
                </div>
            </div>

            @if ($transportRequest->acceptedDriver)
                <div class="transport-notice transport-note-success">
                    <p><strong>Driver assigned:</strong> {{ $transportRequest->acceptedDriver->user->name }}</p>
                    <p class="mb-0">Vehicle: {{ $transportRequest->acceptedVehicle?->name }} - {{ ucfirst($transportRequest->acceptedVehicle?->vehicle_type ?? 'vehicle') }}</p>
                </div>
            @elseif ($transportRequest->status === \App\Models\TransportRequest::STATUS_SCHEDULED)
                <div class="transport-notice transport-note-warning">
                    No driver is currently assigned. This request is scheduled and will be dispatched when drivers are available.
                </div>
            @else
                <div class="transport-notice">
                    Waiting for an available driver to accept. Realtime tracking is only opened while there is active driver/request activity.
                </div>
            @endif
        </article>

        <article class="transport-request-card">
            <h3>Driver offers</h3>
            <div class="transport-stack">
                @forelse ($transportRequest->offers as $offer)
                    <div class="transport-offer">
                        <p><strong>{{ $offer->driver->user->name }} - {{ $offer->vehicle->name }}</strong></p>
                        <p class="muted mb-0">Status: {{ ucfirst($offer->status) }} - Quote: ZAR {{ number_format((float) $offer->quoted_amount, 2) }}</p>
                    </div>
                @empty
                    <p class="muted">No eligible drivers were available when this request was created.</p>
                @endforelse
            </div>
        </article>

        <article class="transport-request-card">
            <h3>Status history</h3>
            <div class="transport-stack">
                @foreach ($transportRequest->statusEvents as $event)
                    <div>
                        <p><strong>{{ ucfirst(str_replace('_', ' ', $event->status)) }}</strong></p>
                        <p class="muted mb-0">{{ $event->notes }} - {{ $event->created_at->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection
