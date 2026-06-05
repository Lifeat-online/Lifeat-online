@extends('layouts.public')

@section('title', $invoice->invoice_number.' | My Invoices')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Invoice {{ $invoice->invoice_number }}</h2>
                <p class="muted">Review invoice totals, linked order details, and payment status.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.invoices.index') }}">Back to invoices</a>
                <a class="button-link" href="{{ route('checkout.show', $order) }}">Open order</a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>Invoice Summary</h3>
                <p><strong>Status:</strong> {{ ucfirst($invoice->status) }}</p>
                <p><strong>Issued:</strong> {{ optional($invoice->issued_at ?: $invoice->created_at)->format('j M Y H:i') ?: '-' }}</p>
                <p><strong>Due:</strong> {{ optional($invoice->due_at)->format('j M Y H:i') ?: '-' }}</p>
                <p><strong>Emailed:</strong> {{ optional($invoice->emailed_at)->format('j M Y H:i') ?: '-' }}</p>
                <p><strong>Subtotal:</strong> {{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</p>
                <p><strong>VAT:</strong> {{ $invoice->currency }} {{ number_format((float) $invoice->vat_amount, 2) }}</p>
                <p><strong>Total:</strong> {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</p>
            </article>

            <article class="card">
                <h3>Order and Payment</h3>
                <p><strong>Order:</strong> {{ $order->order_number }}</p>
                <p><strong>Order type:</strong> {{ $order->renewedSubscription ? 'Subscription renewal' : 'New package purchase' }}</p>
                @if ($order->renewedSubscription)
                    <p><strong>Renewing:</strong> {{ $order->renewedSubscription->package?->name ?: 'Package' }}</p>
                @endif
                <p><strong>Order status:</strong> {{ ucfirst($order->status) }}</p>
                <p><strong>Payment status:</strong> {{ ucfirst($payment?->status ?: 'pending') }}</p>
                @if ($payment?->provider_transaction_id)
                    <p><strong>Transaction reference:</strong> {{ $payment->provider_transaction_id }}</p>
                @endif
                @if ($payment?->failure_reason)
                    <p><strong>Failure reason:</strong> {{ $payment->failure_reason }}</p>
                @endif
                @if ($payment && in_array($payment->status, ['pending', 'failed'], true))
                    <p><a class="button" href="{{ route('checkout.show', $order) }}">Complete payment</a></p>
                @endif
            </article>
        </div>
    </section>

    <section class="section">
        <article class="card">
            <h3>Payment Attempts</h3>
            @forelse ($paymentAttempts as $attempt)
                <p>
                    <strong>{{ ucfirst($attempt->status) }}</strong><br>
                    <span class="muted">
                        {{ ucfirst($attempt->provider) }}
                        @if ($attempt->attempted_at)
                            - {{ $attempt->attempted_at->format('j M Y H:i') }}
                        @endif
                    </span>
                    @if ($attempt->redirect_url && $attempt->status === 'initiated')
                        <br><a class="button-link" href="{{ $attempt->redirect_url }}" rel="noopener">Open PayFast handoff</a>
                    @endif
                </p>
            @empty
                <div class="empty-state">No payment attempts have been generated yet.</div>
            @endforelse
        </article>
    </section>

    <section class="section">
        <article class="card">
            <h3>Invoice Items</h3>
            @forelse ($order->items as $item)
                <p>
                    <strong>{{ $item->name_snapshot }}</strong><br>
                    <span class="muted">{{ $invoice->currency }} {{ number_format((float) $item->unit_price, 2) }}</span>
                    @if ($item->purchasable)
                        <br><span class="muted">Linked {{ class_basename($item->purchasable_type) }}: {{ $item->purchasable->title }}</span>
                    @endif
                </p>
            @empty
                <div class="empty-state">No invoice items recorded.</div>
            @endforelse
        </article>
    </section>
@endsection
