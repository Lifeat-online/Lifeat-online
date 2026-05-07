<p>Hello {{ $voucher->listing?->owner?->name ?: 'Business owner' }},</p>

<p>Your voucher <strong>{{ $voucher->title }}</strong> has reached a usage threshold.</p>

<p>
    Business: <strong>{{ $voucher->listing?->title ?: 'Business' }}</strong><br>
    Threshold: <strong>{{ $thresholdPercent }}%</strong><br>
    Used: <strong>{{ $voucher->redemptions_count }}</strong> of <strong>{{ $voucher->usage_limit }}</strong>
</p>

<p>Review and manage vouchers from your account dashboard.</p>

