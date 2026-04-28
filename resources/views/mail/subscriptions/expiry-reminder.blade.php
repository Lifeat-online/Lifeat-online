<p>Hello {{ $subscription->user?->name ?: 'Customer' }},</p>

<p>Your {{ $subscription->package?->name ?: 'subscription' }} is nearing expiry.</p>

<p>
    Status: {{ ucfirst($subscription->status) }}<br>
    Ends at: {{ optional($subscription->ends_at)->format('j M Y H:i') ?: 'N/A' }}
</p>

<p>Please renew to keep your access active.</p>
