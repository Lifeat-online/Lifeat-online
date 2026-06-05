<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Payment $payment,
    ) {
    }
}
