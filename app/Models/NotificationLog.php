<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'notification_type',
        'notifiable_type',
        'notifiable_id',
        'recipient',
        'status',
        'sent_at',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
