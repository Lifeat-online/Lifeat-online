@extends('layouts.public')

@section('title', 'Order '.$order->order_number.' | Checkout')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Order Summary</h2>
                @if ($order->renewedSubscription)
                    <p class="muted">Renewal for {{ $order->renewedSubscription->package?->name ?: 'subscription package' }}.</p>
                @endif
            </div>
        </div>

        <article class="card">
            <p><strong>Order:</strong> {{ $order->order_number }}</p>
            <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
            <p><strong>Invoice:</strong> {{ $invoice?->invoice_number ?: 'Pending' }}</p>
            <p><strong>Payment Status:</strong> {{ ucfirst($payment?->status ?: 'pending') }}</p>
            @if (session('status'))
                <p class="muted">{{ session('status') }}</p>
            @endif
        </article>
    </section>

    @if ($listingOnboarding)
        <section class="section">
            @include('account.listings._onboarding-checklist', ['onboardingChecklist' => $listingOnboarding])
        </section>
    @endif

    <section class="section">
        <div class="grid grid-2">
            <article class="card">
                <h3>Items</h3>
                @foreach ($order->items as $item)
                    <p>
                        <strong>{{ $item->name_snapshot }}</strong><br>
                        <span class="muted">
                            {{ $item->package?->billing_model ? ucfirst(str_replace('_', ' ', $item->package->billing_model)) : ucfirst(str_replace('_', ' ', $item->billing_model)) }}
                            · {{ $order->currency }} {{ number_format((float) $item->unit_price, 2) }}
                        </span>
                    </p>
                    @if ($item->purchasable)
                        <p class="muted">Linked listing: {{ $item->purchasable->title }}</p>
                    @endif
                @endforeach
            </article>

            <article class="card">
                <h3>Totals</h3>
                <p><strong>Subtotal:</strong> {{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</p>
                <p><strong>VAT:</strong> {{ $order->currency }} {{ number_format((float) $order->vat_amount, 2) }}</p>
                <p><strong>Total:</strong> {{ $order->currency }} {{ number_format((float) $order->total, 2) }}</p>
                @if ($invoice)
                    <form method="post" action="{{ route('checkout.invoice.send', $order) }}" style="margin-top: 1rem;">
                        @csrf
                        <button class="button" type="submit">Email invoice</button>
                    </form>
                @endif
                @if ($payment && $payment->status === 'failed')
                    <form method="post" action="{{ route('checkout.payfast.retry', $order) }}" style="margin-top: 1rem;">
                        @csrf
                        <button class="button" type="submit">Retry Payment</button>
                    </form>
                @endif
            </article>
        </div>
    </section>

    <section class="section">
        <article class="card">
            <h3>Payment Handoff</h3>
            <p class="muted">Generate a PayFast handoff when you are ready to pay. This order keeps a payment-attempt history so you can see whether the latest attempt is pending, failed, or complete.</p>

            @if (! $payment || $payment->status !== 'paid')
                <form method="post" action="{{ route('checkout.payfast.initiate', $order) }}" style="margin-bottom: 1rem;">
                    @csrf
                    <button class="button" type="submit">Prepare PayFast payment</button>
                </form>
            @endif

            @if ($latestAttempt?->redirect_url && $latestAttempt->status === 'initiated')
                <p><a class="button-link" href="{{ $latestAttempt->redirect_url }}" rel="noopener">Open latest PayFast handoff</a></p>
            @endif

            <h4>Attempt History</h4>
            @forelse ($paymentAttempts as $attempt)
                <p>
                    <strong>{{ ucfirst($attempt->status) }}</strong><br>
                    <span class="muted">
                        {{ ucfirst($attempt->provider) }}
                        @if ($attempt->attempted_at)
                            - {{ $attempt->attempted_at->format('j M Y H:i') }}
                        @endif
                    </span>
                </p>
            @empty
                <div class="empty-state">No payment attempts have been generated yet.</div>
            @endforelse
        </article>
    </section>
@endsection
