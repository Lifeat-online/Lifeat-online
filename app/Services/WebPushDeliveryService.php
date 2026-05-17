<?php

namespace App\Services;

use App\Models\BrowserPushSubscription;
use App\Models\PushCampaign;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class WebPushDeliveryService
{
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
                'subject' => config('services.webpush.subject') ?: config('app.url'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
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

    private function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
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
