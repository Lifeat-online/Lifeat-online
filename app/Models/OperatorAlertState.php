<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorAlertState extends Model
{
    use HasFactory;

    protected $table = 'operator_alert_states';

    protected $fillable = [
        'user_id',
        'fingerprint',
        'target',
        'severity',
        'retries_sent',
        'first_seen_at',
        'last_sent_at',
        'acknowledged_at',
        'last_payload',
    ];

    protected function casts(): array
    {
        return [
            'retries_sent' => 'integer',
            'first_seen_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'last_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeForSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
}
