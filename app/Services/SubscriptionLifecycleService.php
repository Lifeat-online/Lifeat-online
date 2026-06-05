<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Listing;
use App\Models\Subscription;
use App\Models\SubscriptionReminder;
use App\Support\Logging\OperationalLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionLifecycleService
{
    public function extend(Subscription $subscription, int $days): Subscription
    {
        $updated = DB::transaction(function () use ($subscription, $days) {
            $subscription->loadMissing(['entitlements', 'subscribable']);

            $base = $subscription->ends_at && $subscription->ends_at->isFuture()
                ? $subscription->ends_at->copy()
                : now();

            $newEndsAt = $base->addDays($days);

            $subscription->update([
                'status' => 'active',
                'starts_at' => $subscription->starts_at ?: now(),
                'ends_at' => $newEndsAt,
                'renews_at' => $newEndsAt,
            ]);

            foreach ($subscription->entitlements as $entitlement) {
                $entitlement->update([
                    'status' => 'active',
                    'active_from' => $entitlement->active_from ?: now(),
                    'active_until' => $newEndsAt,
                ]);
            }

            $this->syncSubscribableState($subscription, $newEndsAt, true);

            return $subscription->fresh(['entitlements', 'subscribable']);
        });

        OperationalLog::info('subscription.extended', $this->subscriptionContext($updated, [
            'extension_days' => $days,
        ]));

        return $updated;
    }

    public function suspend(Subscription $subscription, ?string $reason = null): Subscription
    {
        $updated = DB::transaction(function () use ($subscription) {
            $subscription->loadMissing(['entitlements', 'subscribable']);

            $subscription->update([
                'status' => 'suspended',
                'renews_at' => null,
                'ends_at' => now(),
            ]);

            foreach ($subscription->entitlements as $entitlement) {
                $entitlement->update([
                    'status' => 'suspended',
                    'active_until' => now(),
                ]);
            }

            $this->syncSubscribableState($subscription, now(), false);

            return $subscription->fresh(['entitlements', 'subscribable']);
        });

        OperationalLog::warning('subscription.suspended', $this->subscriptionContext($updated, [
            'has_reason' => filled($reason),
        ]));

        return $updated;
    }

    public function expire(Subscription $subscription): Subscription
    {
        $updated = DB::transaction(function () use ($subscription) {
            $subscription->loadMissing(['entitlements', 'subscribable']);

            $subscription->update([
                'status' => 'expired',
                'renews_at' => null,
                'ends_at' => $subscription->ends_at ?: now(),
            ]);

            foreach ($subscription->entitlements as $entitlement) {
                $entitlement->update([
                    'status' => 'expired',
                    'active_until' => now(),
                ]);
            }

            $this->syncSubscribableState($subscription, now(), false);

            return $subscription->fresh(['entitlements', 'subscribable']);
        });

        OperationalLog::warning('subscription.expired', $this->subscriptionContext($updated));

        return $updated;
    }

    public function logReminder(
        Subscription $subscription,
        string $type = 'expiry_notice',
        string $channel = 'email',
        string $status = 'logged'
    ): SubscriptionReminder
    {
        $reminder = $subscription->reminders()->create([
            'reminder_type' => $type,
            'channel' => $channel,
            'status' => $status,
            'sent_at' => now(),
        ]);

        OperationalLog::info('subscription.reminder_logged', $this->subscriptionContext($subscription, [
            'subscription_reminder_id' => $reminder->id,
            'reminder_type' => $type,
            'channel' => $channel,
            'reminder_status' => $status,
        ]));

        return $reminder;
    }

    private function syncSubscribableState(Subscription $subscription, Carbon $until, bool $active): void
    {
        $entity = $subscription->subscribable;

        if ($entity instanceof Listing) {
            $entity->update([
                'active_subscription_id' => $active ? $subscription->id : null,
                'package_expires_at' => $until,
                'status' => $active ? 'published' : 'draft',
                'published_at' => $active ? ($entity->published_at ?: now()) : $entity->published_at,
            ]);

            return;
        }

        if ($entity instanceof Event) {
            $entity->update([
                'active_subscription_id' => $active ? $subscription->id : null,
                'package_expires_at' => $until,
                'status' => $active ? 'published' : 'draft',
                'published_at' => $active ? ($entity->published_at ?: now()) : $entity->published_at,
            ]);
        }
    }

    private function subscriptionContext(Subscription $subscription, array $extra = []): array
    {
        return array_merge([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'package_id' => $subscription->package_id,
            'subscribable_type' => $subscription->subscribable_type,
            'subscribable_id' => $subscription->subscribable_id,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'renews_at' => $subscription->renews_at,
            'payment_id' => $subscription->payment_id,
        ], $extra);
    }
}
