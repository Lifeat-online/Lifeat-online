<?php

namespace App\Events;

use App\Models\NotificationLog;
use App\Models\PushCampaign;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PushCampaignDispatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PushCampaign $pushCampaign,
        public readonly NotificationLog $notificationLog,
    ) {
    }
}
