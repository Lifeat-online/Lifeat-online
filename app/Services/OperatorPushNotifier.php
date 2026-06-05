<?php

namespace App\Services;

use App\Models\BrowserPushSubscription;
use App\Models\OperatorAlertState;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class OperatorPushNotifier
{
    public function __construct(
        private readonly VapidKeySetupService $vapidKeys,
    ) {
    }

    /**
     * Resolve the recipients for a given alert target.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(string $target): Collection
    {
        if (! (bool) config('ops.enabled', false)) {
            return collect();
        }

        $targetConfig = (array) config("ops.targets.{$target}", []);
        if ($targetConfig === []) {
            return collect();
        }

        $category = (string) ($targetConfig['category'] ?? 'operational');
        $devIsAdmin = (bool) config('ops.dev_is_admin', true);

        $explicit = collect((array) config('ops.explicit_user_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->all();

        $roles = ['admin', 'super_admin'];
        if ($devIsAdmin) {
            $roles = array_merge($roles, ['dev', 'developer']);
        }
        if ($category === 'operational') {
            $roles[] = 'support';
        }

        $query = User::query()
            ->whereIn('role', $roles)
            ->whereHas('browserPushSubscriptions', fn ($q) => $q->whereNull('revoked_at'));

        if ($explicit !== []) {
            $query->orWhereIn('id', $explicit)
                ->whereHas('browserPushSubscriptions', fn ($q) => $q->whereNull('revoked_at'));
        }

        return $query->distinct()->orderBy('id')->get();
    }

    /**
     * Send a push notification to every eligible operator for the target.
     *
     * @param  array<string, mixed>  $data
     * @return array{configured: bool, attempted: int, sent: int, failed: int, expired: int, deduplicated: int}
     */
    public function send(string $target, string $title, string $body, string $severity = 'info', ?string $url = null, array $data = []): array
    {
        if (! (bool) config('ops.enabled', false)) {
            return $this->result(0, 0, 0, 0, deduplicated: 0, configured: $this->isConfigured());
        }

        $targetConfig = (array) config("ops.targets.{$target}", []);
        if ($targetConfig === []) {
            Log::warning('lifeat.ops.unknown_target', ['target' => $target]);

            return $this->result(0, 0, 0, 0, deduplicated: 0, configured: $this->isConfigured());
        }

        $recipients = $this->recipientsFor($target);
        if ($recipients->isEmpty()) {
            return $this->result(0, 0, 0, 0, deduplicated: 0, configured: $this->isConfigured());
        }

        $fingerprint = $this->fingerprint($target, $title, $body, $data);
        $ackWindow = (int) config('ops.ack_window_minutes', 30);
        $retryAfter = (int) config('ops.retry_after_minutes', 15);
        $maxRetries = (int) config('ops.max_retries', 4);

        $deduplicated = 0;
        $attempted = 0;
        $sent = 0;
        $failed = 0;
        $expired = 0;
        $payload = ['title' => $title, 'body' => $body, 'url' => $url, 'data' => array_merge($data, [
            'target' => $target,
            'severity' => $severity,
            'fingerprint' => $fingerprint,
        ])];

        $webPush = $this->isConfigured() ? $this->webPush() : null;

        foreach ($recipients as $user) {
            $state = OperatorAlertState::firstOrNew([
                'user_id' => $user->id,
                'fingerprint' => $fingerprint,
            ]);
            $state->target = $target;
            $state->severity = $severity;
            $state->last_payload = $payload;

            if ($this->shouldSkip($state, $severity, $ackWindow, $retryAfter, $maxRetries)) {
                $deduplicated++;
                continue;
            }

            $subscriptions = BrowserPushSubscription::active()
                ->where('user_id', $user->id)
                ->get();

            if ($webPush !== null && $subscriptions->isNotEmpty()) {
                foreach ($subscriptions as $subscription) {
                    $attempted++;
                    $webPush->queueNotification(
                        WebPushSubscription::create([
                            'endpoint' => $subscription->endpoint,
                            'publicKey' => $subscription->public_key,
                            'authToken' => $subscription->auth_token,
                            'contentEncoding' => $subscription->content_encoding,
                        ]),
                        json_encode($payload, JSON_THROW_ON_ERROR)
                    );
                }
            }

            $state->retries_sent = ($state->retries_sent ?? 0) + 1;
            $state->last_sent_at = now();
            $state->save();
        }

        if ($webPush !== null) {
            foreach ($webPush->flush() as $report) {
                $endpointHash = BrowserPushSubscription::endpointHash($report->getEndpoint());
                $subscription = BrowserPushSubscription::where('endpoint_hash', $endpointHash)->first();

                if ($report->isSuccess()) {
                    $sent++;
                    $subscription?->forceFill([
                        'last_seen_at' => now(),
                        'failure_count' => 0,
                    ])->save();
                    continue;
                }

                $failed++;
                if ($report->isSubscriptionExpired()) {
                    $expired++;
                }
                $subscription?->markFailed($report->isSubscriptionExpired());
                Log::warning('lifeat.ops.push_delivery_failed', [
                    'endpoint_hash' => $subscription?->endpoint_hash,
                    'reason' => $report->getReason(),
                    'expired' => $report->isSubscriptionExpired(),
                ]);
            }
        } else {
            Log::info('lifeat.ops.vapid_not_configured', [
                'target' => $target,
                'fingerprint' => $fingerprint,
                'states_persisted' => $recipients->count(),
            ]);
        }

        Log::info('lifeat.ops.alert_sent', [
            'target' => $target,
            'severity' => $severity,
            'fingerprint' => $fingerprint,
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'expired' => $expired,
            'deduplicated' => $deduplicated,
            'configured' => $webPush !== null,
        ]);

        return $this->result($attempted, $sent, $failed, $expired, $deduplicated, $webPush !== null);
    }

    public function acknowledge(int $userId, string $fingerprint): bool
    {
        $state = OperatorAlertState::where('user_id', $userId)
            ->where('fingerprint', $fingerprint)
            ->first();

        if (! $state) {
            return false;
        }

        $state->acknowledged_at = now();
        $state->save();

        return true;
    }

    public function isConfigured(): bool
    {
        return filled($this->vapidKeys->publicKey())
            && filled($this->vapidKeys->privateKey());
    }

    private function shouldSkip(OperatorAlertState $state, string $severity, int $ackWindow, int $retryAfter, int $maxRetries): bool
    {
        if ($state->acknowledged_at !== null) {
            return true;
        }

        $retries = (int) $state->retries_sent;

        if ($severity !== 'critical' && $retries > 0) {
            return true;
        }

        if ($retries >= $maxRetries) {
            return true;
        }

        if ($state->last_sent_at === null) {
            return false;
        }

        $minutesSinceLast = (int) $state->last_sent_at->diffInMinutes(now());

        if ($severity === 'critical') {
            return $minutesSinceLast < $retryAfter;
        }

        return $minutesSinceLast < $ackWindow;
    }

    private function fingerprint(string $target, string $title, string $body, array $data): string
    {
        return hash('sha256', implode('|', [
            $target,
            $title,
            $body,
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]));
    }

    private function webPush(): WebPush
    {
        set_error_handler(static function (int $severity, string $message): bool {
            return $severity === E_USER_NOTICE
                && str_contains($message, 'GMP or BCMath extension');
        });

        try {
            return new WebPush([
                'VAPID' => [
                    'subject' => $this->vapidKeys->subject(),
                    'publicKey' => $this->vapidKeys->publicKey(),
                    'privateKey' => $this->vapidKeys->privateKey(),
                ],
            ]);
        } finally {
            restore_error_handler();
        }
    }

    private function result(int $attempted, int $sent, int $failed, int $expired, int $deduplicated, bool $configured): array
    {
        return [
            'configured' => $configured,
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'expired' => $expired,
            'deduplicated' => $deduplicated,
        ];
    }
}
