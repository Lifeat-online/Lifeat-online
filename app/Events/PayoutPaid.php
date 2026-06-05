<?php

namespace App\Events;

use App\Models\PayoutRequest;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayoutPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PayoutRequest $payoutRequest,
        public readonly ?WalletLedgerEntry $ledgerEntry = null,
    ) {
    }
}
