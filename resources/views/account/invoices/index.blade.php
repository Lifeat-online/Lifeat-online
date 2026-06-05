@extends('layouts.public')

@section('title', 'My Invoices | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>My Invoices</h2>
                <p class="muted">Review invoice history connected to your orders and package purchases.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.index') }}">Back to account</a>
            </div>
        </div>

        <form method="get" class="card form-grid">
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach (['draft', 'issued', 'paid', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button class="button" type="submit">Filter</button>
                <a class="button-link" href="{{ route('account.invoices.index') }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="section">
        @forelse ($invoices as $invoice)
            @php
                $latestPayment = $invoice->order?->payments?->sortByDesc('id')->first();
                $isRenewal = (bool) $invoice->order?->renewed_subscription_id;
            @endphp
            @if ($loop->first)<div class="grid grid-2">@endif
            <article class="card">
                <h3><a href="{{ route('account.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></h3>
                <p class="muted">
                    Order {{ $invoice->order?->order_number ?: '-' }}
                    @if ($isRenewal)
                        - Renewal
                    @endif
                </p>
                <p>{{ ucfirst($invoice->status) }} · {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</p>
                <p class="muted">Payment {{ ucfirst($latestPayment?->status ?: 'pending') }}</p>
                <p class="muted">Issued {{ optional($invoice->issued_at ?: $invoice->created_at)->format('j M Y') ?: '-' }}</p>
                @if ($invoice->order && $latestPayment && in_array($latestPayment->status, ['pending', 'failed'], true))
                    <p><a class="button-link" href="{{ route('checkout.show', $invoice->order) }}">Complete payment</a></p>
                @endif
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">No invoices match your filters.</div>
        @endforelse

        <div style="margin-top: 1rem;">{{ $invoices->links() }}</div>
    </section>
@endsection
