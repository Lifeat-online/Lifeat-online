<?php

namespace App\Events;

use App\Models\MallOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MallOrderPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public MallOrder $order) {}
}
