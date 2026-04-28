<?php

namespace App\Services;

use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Model;

class NotificationLogService
{
    public function log(
        string $notificationType,
        ?Model $notifiable,
        ?string $recipient,
        string $channel = 'email',
        string $status = 'sent',
        array $meta = []
    ): NotificationLog {
        return NotificationLog::create([
            'channel' => $channel,
            'notification_type' => $notificationType,
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
            'recipient' => $recipient,
            'status' => $status,
            'sent_at' => now(),
            'meta_json' => $meta,
        ]);
    }
}
