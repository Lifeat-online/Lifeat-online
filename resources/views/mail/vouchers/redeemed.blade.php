<p>Hello {{ $redemption->customer?->name ?: 'Customer' }},</p>

<p>Your voucher has been redeemed successfully.</p>

<p>
    Business: <strong>{{ $redemption->voucher?->listing?->title ?: 'Business' }}</strong><br>
    Voucher: <strong>{{ $redemption->voucher?->title ?: 'Voucher' }}</strong><br>
    Code: <strong>{{ $redemption->code }}</strong>
</p>

@if ($redemption->voucher?->terms)
    <p>Terms:</p>
    <p>{!! nl2br(e($redemption->voucher->terms)) !!}</p>
@endif

<p>Present this code to business staff and ask them to use it at checkout.</p>

