<h1>New mall order</h1>
<p>{{ $order->store?->name }} received order {{ $order->order_number }}.</p>
<p>Vendor amount: R {{ $order->vendor_amount }}</p>
<ul>
    @foreach ($order->items as $item)
        <li>{{ $item->product_name }} x {{ $item->quantity }} - R {{ $item->line_total }}</li>
    @endforeach
</ul>
