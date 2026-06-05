<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class CampaignTrackingEvent extends Model
{
    public const TYPE_IMPRESSION = 'impression';
    public const TYPE_CLICK = 'click';
    public const TYPE_PUSH_OPEN = 'push_open';

    protected $fillable = [
        'trackable_type',
        'trackable_id',
        'event_type',
        'tracking_token',
        'occurred_at',
        'ip_hash',
        'user_agent_hash',
        'referrer',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(Model $trackable, string $eventType, Request $request): bool
    {
        $attributes = [
            'trackable_type' => $trackable->getMorphClass(),
            'trackable_id' => $trackable->getKey(),
            'event_type' => $eventType,
        ];

        $values = self::requestSnapshot($request);
        $token = self::trackingToken($request);

        if ($token !== null) {
            $event = self::firstOrCreate(
                $attributes + ['tracking_token' => $token],
                $values
            );

            return $event->wasRecentlyCreated;
        }

        self::create($attributes + $values);

        return true;
    }

    private static function requestSnapshot(Request $request): array
    {
        $ip = $request->ip();
        $userAgent = (string) $request->userAgent();
        $referrer = (string) $request->headers->get('referer', '');

        return [
            'occurred_at' => now(),
            'ip_hash' => $ip ? hash('sha256', $ip) : null,
            'user_agent_hash' => $userAgent !== '' ? hash('sha256', $userAgent) : null,
            'referrer' => $referrer !== '' ? mb_substr($referrer, 0, 2048) : null,
            'metadata_json' => [
                'path' => $request->path(),
                'query' => collect($request->query())->except(['t', 'token'])->all(),
            ],
        ];
    }

    private static function trackingToken(Request $request): ?string
    {
        $token = trim((string) ($request->query('t') ?: $request->query('token')));

        if ($token === '') {
            return null;
        }

        $token = preg_replace('/[^A-Za-z0-9._:-]/', '', $token) ?: '';

        return $token !== '' ? mb_substr($token, 0, 80) : null;
    }
}
