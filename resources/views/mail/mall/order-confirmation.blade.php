<h1>Order confirmed</h1>
<p>Your Life@ Mall order {{ $order->order_number }} at {{ $order->store?->name }} has been paid.</p>
<p>Total: R {{ $order->total }}</p>
<ul>
    @foreach ($order->items as $item)
        <li>{{ $item->product_name }} x {{ $item->quantity }} - R {{ $item->line_total }}</li>
    @endforeach
</ul>
