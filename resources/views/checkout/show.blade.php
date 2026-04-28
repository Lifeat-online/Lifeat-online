@extends('layouts.public')

@section('title', 'Order '.$order->order_number.' | Checkout')

@section('content')
    <section class="section">
        <div class="section-head">
            <h2>Order Summary</h2>
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
                        <button class="button" type="submit">Mark Invoice Sent</button>
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
            <h3>PayFast Foundation</h3>
            <p class="muted">This order is ready for a PayFast handoff. For now, the callback endpoint below can be used to simulate PayFast success or failure while the real gateway integration is being built.</p>

            <form method="post" action="{{ route('checkout.payfast.initiate', $order) }}" style="margin-bottom: 1rem;">
                @csrf
                <button class="button" type="submit">Generate PayFast Payload</button>
            </form>

            @if ($latestAttempt)
                <div class="card" style="margin-bottom: 1rem; background: rgba(29, 78, 216, 0.04);">
                    <p><strong>Latest Attempt:</strong> {{ ucfirst($latestAttempt->status) }}</p>
                    <p><strong>Redirect URL:</strong> {{ $latestAttempt->redirect_url }}</p>
                    @if (($latestAttempt->request_payload_json['signature'] ?? null))
                        <p><strong>Signature:</strong> {{ $latestAttempt->request_payload_json['signature'] }}</p>
                    @endif
                    <pre style="overflow:auto; white-space:pre-wrap;">{{ json_encode($latestAttempt->request_payload_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif

            <form method="post" action="{{ route('checkout.payfast.callback') }}" class="form-grid">
                @csrf
                <input type="hidden" name="order_number" value="{{ $order->order_number }}">
                <div>
                    <label for="status">Callback Status</label>
                    <select id="status" name="status">
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div>
                    <label for="provider_transaction_id">Transaction Reference</label>
                    <input id="provider_transaction_id" name="provider_transaction_id" value="{{ $payment?->provider_transaction_id }}">
                </div>
                <div>
                    <label for="signature">Signature</label>
                    <input id="signature" name="signature" value="{{ $latestAttempt->request_payload_json['signature'] ?? '' }}">
                </div>
                <div>
                    <button class="button" type="submit">Simulate Callback</button>
                </div>
            </form>
        </article>
    </section>
@endsection
