<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrowserPushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'last_seen_at',
        'revoked_at',
        'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'failure_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public static function endpointHash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function markFailed(bool $expired = false): void
    {
        $updates = [
            'failure_count' => $this->failure_count + 1,
        ];

        if ($expired || $updates['failure_count'] >= 5) {
            $updates['revoked_at'] = now();
        }

        $this->forceFill($updates)->save();
    }
}
