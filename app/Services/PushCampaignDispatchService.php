<?php

namespace App\Services;

use App\Events\PushCampaignDispatched;
use App\Models\PushCampaign;
use App\Models\NotificationLog;
use App\Support\Logging\OperationalLog;
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

        try {
            $this->ensureDispatchable($campaign);
        } catch (RuntimeException $exception) {
            OperationalLog::warning('campaign.push_dispatch_rejected', $this->campaignContext($campaign, [
                'rejection_reason' => $exception->getMessage(),
            ]));

            throw $exception;
        }

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

        OperationalLog::info('campaign.push_dispatched', $this->campaignContext($campaign, [
            'notification_log_id' => $notification->id,
            'sent_at' => $dispatchedAt,
            'web_push_configured' => $delivery['configured'] ?? null,
            'web_push_attempted' => $delivery['attempted'] ?? null,
            'web_push_sent' => $delivery['sent'] ?? null,
            'web_push_failed' => $delivery['failed'] ?? null,
            'web_push_expired' => $delivery['expired'] ?? null,
        ]));

        PushCampaignDispatched::dispatch($campaign->fresh(['listing', 'event', 'activeSubscription']), $notification);

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

    private function campaignContext(PushCampaign $campaign, array $extra = []): array
    {
        return array_merge([
            'push_campaign_id' => $campaign->id,
            'listing_id' => $campaign->listing_id,
            'event_id' => $campaign->event_id,
            'user_id' => $campaign->user_id,
            'status' => $campaign->status,
            'audience_scope' => $campaign->audience_scope,
            'target_city' => $campaign->target_city,
            'target_region' => $campaign->target_region,
            'schedule_at' => $campaign->schedule_at,
            'active_subscription_id' => $campaign->active_subscription_id,
        ], $extra);
    }
}
