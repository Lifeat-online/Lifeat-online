<?php

namespace App\Services;

use App\Models\PushCampaign;
use App\Models\NotificationLog;
use RuntimeException;

class PushCampaignDispatchService
{
    public function __construct(
        private readonly NotificationLogService $notificationLogService,
        private readonly WebPushDeliveryService $webPushDeliveryService,
    ) {
    }

    public function dispatch(PushCampaign $campaign): NotificationLog
    {
        $campaign->loadMissing(['listing', 'event', 'activeSubscription.package']);

        $this->ensureDispatchable($campaign);

        $dispatchedAt = now();
        $delivery = $this->webPushDeliveryService->sendCampaign($campaign);

        $notification = $this->notificationLogService->log(
            'push_campaign_sent',
            $campaign,
            $campaign->audienceSummary(),
            'push',
            'sent',
            [
                'campaign_title' => $campaign->title,
                'headline' => $campaign->headline,
                'message' => $campaign->message,
                'listing_title' => $campaign->listing?->title,
                'listing_slug' => $campaign->listing?->slug,
                'event_title' => $campaign->event?->title,
                'event_slug' => $campaign->event?->slug,
                'audience_scope' => $campaign->audience_scope,
                'target_city' => $campaign->target_city,
                'target_region' => $campaign->target_region,
                'radius_km' => $campaign->radius_km,
                'scheduled_for' => optional($campaign->schedule_at)->toIso8601String(),
                'dispatched_at' => $dispatchedAt->toIso8601String(),
                'package_name' => $campaign->activeSubscription?->package?->name,
                'web_push' => $delivery,
            ]
        );

        $campaign->forceFill([
            'sent_at' => $dispatchedAt,
        ])->save();

        return $notification;
    }

    private function ensureDispatchable(PushCampaign $campaign): void
    {
        if ($campaign->sent_at) {
            throw new RuntimeException('This push campaign has already been dispatched.');
        }

        if (! in_array($campaign->status, ['active', 'scheduled'], true)) {
            throw new RuntimeException('Only active or scheduled push campaigns can be dispatched.');
        }

        if (! $campaign->linkedListingHasActiveEntitlement()) {
            throw new RuntimeException('The linked business listing needs an active package before push delivery can run.');
        }

        if (! $campaign->hasActivePushEntitlement()) {
            throw new RuntimeException('This push campaign needs an active push package before delivery can run.');
        }

        if ($campaign->status === 'scheduled' && $campaign->schedule_at && $campaign->schedule_at->isFuture()) {
            throw new RuntimeException('This push campaign is scheduled for a future time.');
        }
    }
}
