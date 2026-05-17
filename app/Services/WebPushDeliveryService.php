<?php

namespace App\Services;

use App\Models\BrowserPushSubscription;
use App\Models\PushCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class WebPushDeliveryService
{
    public function __construct(
        private readonly VapidKeySetupService $vapidKeys,
    ) {
    }

    public function sendCampaign(PushCampaign $campaign): array
    {
        if (! $this->isConfigured()) {
            return [
                'configured' => false,
                'attempted' => 0,
                'sent' => 0,
                'failed' => 0,
                'expired' => 0,
            ];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->vapidKeys->subject(),
                'publicKey' => $this->vapidKeys->publicKey(),
                'privateKey' => $this->vapidKeys->privateKey(),
            ],
        ]);

        $payload = json_encode($this->payloadForCampaign($campaign), JSON_THROW_ON_ERROR);
        $attempted = 0;
        $sent = 0;
        $failed = 0;
        $expired = 0;

        BrowserPushSubscription::active()
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($webPush, $payload, &$attempted): void {
                foreach ($subscriptions as $subscription) {
                    $attempted++;
                    $webPush->queueNotification(
                        WebPushSubscription::create([
                            'endpoint' => $subscription->endpoint,
                            'publicKey' => $subscription->public_key,
                            'authToken' => $subscription->auth_token,
                            'contentEncoding' => $subscription->content_encoding,
                        ]),
                        $payload
                    );
                }
            });

        foreach ($webPush->flush() as $report) {
            $subscription = BrowserPushSubscription::where('endpoint_hash', BrowserPushSubscription::endpointHash($report->getEndpoint()))->first();

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

            Log::warning('Browser push delivery failed.', [
                'endpoint_hash' => $subscription?->endpoint_hash,
                'reason' => $report->getReason(),
                'expired' => $report->isSubscriptionExpired(),
            ]);
        }

        return [
            'configured' => true,
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'expired' => $expired,
        ];
    }

    public function sendTest(User $user, array $payload): array
    {
        return $this->sendManual($user, $payload, 'self');
    }

    public function sendManual(User $sender, array $payload, string $audience = 'all'): array
    {
        if (! $this->isConfigured()) {
            return [
                'configured' => false,
                'attempted' => 0,
                'sent' => 0,
                'failed' => 0,
                'expired' => 0,
            ];
        }

        $webPush = $this->webPush();
        $encodedPayload = json_encode([
            'title' => $payload['title'],
            'body' => $payload['body'],
            'url' => $payload['url'],
            'tag' => 'admin-manual-push-'.$sender->getKey().'-'.now()->timestamp,
            'icon' => asset('pwa/icon-192.png'),
            'badge' => asset('pwa/favicon-32x32.png'),
            'data' => [
                'manual' => true,
                'audience' => $audience,
                'sent_by_user_id' => $sender->getKey(),
            ],
        ], JSON_THROW_ON_ERROR);

        $attempted = 0;
        $query = BrowserPushSubscription::active()->orderBy('id');

        if ($audience === 'self') {
            $query->where('user_id', $sender->getKey());
        }

        $query->chunkById(100, function ($subscriptions) use ($webPush, $encodedPayload, &$attempted): void {
            foreach ($subscriptions as $subscription) {
                $attempted++;
                $webPush->queueNotification(
                    WebPushSubscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding,
                    ]),
                    $encodedPayload
                );
            }
        });

        return $this->flush($webPush, $attempted);
    }

    public function isConfigured(): bool
    {
        return filled($this->vapidKeys->publicKey())
            && filled($this->vapidKeys->privateKey());
    }

    private function webPush(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => $this->vapidKeys->subject(),
                'publicKey' => $this->vapidKeys->publicKey(),
                'privateKey' => $this->vapidKeys->privateKey(),
            ],
        ]);
    }

    private function flush(WebPush $webPush, int $attempted): array
    {
        $sent = 0;
        $failed = 0;
        $expired = 0;

        foreach ($webPush->flush() as $report) {
            $subscription = BrowserPushSubscription::where('endpoint_hash', BrowserPushSubscription::endpointHash($report->getEndpoint()))->first();

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

            Log::warning('Browser push delivery failed.', [
                'endpoint_hash' => $subscription?->endpoint_hash,
                'reason' => $report->getReason(),
                'expired' => $report->isSubscriptionExpired(),
            ]);
        }

        return [
            'configured' => true,
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'expired' => $expired,
        ];
    }

    private function payloadForCampaign(PushCampaign $campaign): array
    {
        $campaign->loadMissing(['listing', 'event']);

        return [
            'title' => $campaign->headline ?: $campaign->title,
            'body' => $campaign->message,
            'url' => route('ad-tracking.push-open', $campaign),
            'tag' => 'push-campaign-'.$campaign->getKey(),
            'icon' => asset('pwa/icon-192.png'),
            'badge' => asset('pwa/favicon-32x32.png'),
            'data' => [
                'campaign_id' => $campaign->getKey(),
                'campaign_slug' => $campaign->slug,
                'listing_slug' => $campaign->listing?->slug,
                'event_slug' => $campaign->event?->slug,
            ],
        ];
    }
}
