<p>Hello {{ $order->user?->name ?: 'Customer' }},</p>

<p>Your renewal order <strong>{{ $order->order_number }}</strong> is still awaiting payment.</p>

<p>
    Total: {{ $order->currency }} {{ number_format((float) $order->total, 2) }}<br>
    Status: {{ ucfirst($order->status) }}
</p>

<p>Please return to checkout to complete the renewal.</p>
