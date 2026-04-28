<p>Hello {{ $invoice->order?->user?->name ?: 'Customer' }},</p>

<p>Your invoice <strong>{{ $invoice->invoice_number }}</strong> is ready.</p>

<p>
    Status: {{ ucfirst($invoice->status) }}<br>
    Total: {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}
</p>

@if ($invoice->order)
    <p>Order number: {{ $invoice->order->order_number }}</p>
@endif

<p>Thank you for using Life Platform.</p>
