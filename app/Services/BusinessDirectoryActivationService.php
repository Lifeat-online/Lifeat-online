<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\Entitlement;
use App\Models\Event;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PushCampaign;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class BusinessDirectoryActivationService
{
    public function activateForPayment(Payment $payment): void
    {
        $payment->loadMissing(['order.items.package.type']);

        DB::transaction(function () use ($payment) {
            foreach ($payment->order->items as $item) {
                $this->activateOrderItem($item, $payment);
            }

            $payment->order->update([
                'status' => 'paid',
                'placed_at' => $payment->paid_at ?: now(),
            ]);

            $payment->order->invoices()->where('status', 'draft')->update([
                'status' => 'paid',
                'issued_at' => now(),
            ]);
        });
    }

    private function activateOrderItem(OrderItem $item, Payment $payment): void
    {
        if (! $item->package) {
            return;
        }

        if ($item->package->type?->slug === 'business_directory') {
            $this->activateBusinessDirectoryItem($item, $payment);
            return;
        }

        if ($item->package->type?->slug === 'event_package') {
            $this->activateEventPackageItem($item, $payment);
            return;
        }

        if ($item->package->type?->slug === 'advert_package') {
            $this->activateAdvertPackageItem($item, $payment);
            return;
        }

        if ($item->package->type?->slug === 'push_campaign') {
            $this->activatePushCampaignItem($item, $payment);
        }
    }

    private function activateBusinessDirectoryItem(OrderItem $item, Payment $payment): void
    {
        $listing = $item->purchasable;

        if (! $listing instanceof Listing) {
            return;
        }

        $startsAt = $item->starts_at ?: now();
        $endsAt = $item->ends_at ?: $startsAt->copy()->addDays($item->package->duration_days);

        $subscription = Subscription::create([
            'user_id' => $payment->user_id ?: $listing->user_id,
            'package_id' => $item->package_id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'renews_at' => $endsAt,
            'renewal_mode' => $item->billing_model === 'six_monthly' ? 'manual' : 'manual',
            'payment_id' => $payment->id,
        ]);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => Listing::class,
            'entitled_id' => $listing->id,
            'entitlement_code' => 'business_directory',
            'active_from' => $startsAt,
            'active_until' => $endsAt,
            'status' => 'active',
        ]);

        $listing->update([
            'status' => 'published',
            'published_at' => $listing->published_at ?: now(),
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $endsAt,
            'source_channel' => $item->package->is_self_service ? 'self_service' : ($listing->source_channel ?: 'staff_assisted'),
        ]);
    }

    private function activateEventPackageItem(OrderItem $item, Payment $payment): void
    {
        $event = $item->purchasable;

        if (! $event instanceof Event) {
            return;
        }

        if (! $event->listing || ! $event->listing->hasActiveBusinessEntitlement()) {
            return;
        }

        $startsAt = $item->starts_at ?: now();
        $endsAt = $item->ends_at ?: $startsAt->copy()->addDays($item->package->duration_days);

        $subscription = Subscription::create([
            'user_id' => $payment->user_id ?: $event->user_id,
            'package_id' => $item->package_id,
            'subscribable_type' => Event::class,
            'subscribable_id' => $event->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'renews_at' => $item->billing_model === 'monthly' ? $endsAt : null,
            'renewal_mode' => $item->billing_model === 'monthly' ? 'manual' : 'manual',
            'payment_id' => $payment->id,
        ]);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => Event::class,
            'entitled_id' => $event->id,
            'entitlement_code' => $item->package->entitlementCode(),
            'active_from' => $startsAt,
            'active_until' => $endsAt,
            'status' => 'active',
        ]);

        $event->update([
            'status' => 'published',
            'published_at' => $event->published_at ?: now(),
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $endsAt,
        ]);
    }

    private function activateAdvertPackageItem(OrderItem $item, Payment $payment): void
    {
        $campaign = $item->purchasable;

        if (! $campaign instanceof AdCampaign) {
            return;
        }

        if (! $campaign->listing || ! $campaign->listing->hasActiveBusinessEntitlement()) {
            return;
        }

        $startsAt = $item->starts_at ?: now();
        $endsAt = $item->ends_at ?: $startsAt->copy()->addDays($item->package->duration_days);

        $subscription = Subscription::create([
            'user_id' => $payment->user_id ?: $campaign->user_id,
            'package_id' => $item->package_id,
            'subscribable_type' => AdCampaign::class,
            'subscribable_id' => $campaign->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'renews_at' => $item->billing_model === 'monthly' ? $endsAt : null,
            'renewal_mode' => 'manual',
            'payment_id' => $payment->id,
        ]);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => AdCampaign::class,
            'entitled_id' => $campaign->id,
            'entitlement_code' => $item->package->entitlementCode(),
            'active_from' => $startsAt,
            'active_until' => $endsAt,
            'status' => 'active',
        ]);

        $campaign->update([
            'status' => 'active',
            'published_at' => $campaign->published_at ?: now(),
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $endsAt,
        ]);
    }

    private function activatePushCampaignItem(OrderItem $item, Payment $payment): void
    {
        $campaign = $item->purchasable;

        if (! $campaign instanceof PushCampaign) {
            return;
        }

        if (! $campaign->listing || ! $campaign->listing->hasActiveBusinessEntitlement()) {
            return;
        }

        $startsAt = $item->starts_at ?: now();
        $endsAt = $item->ends_at ?: $startsAt->copy()->addDays($item->package->duration_days);

        $subscription = Subscription::create([
            'user_id' => $payment->user_id ?: $campaign->user_id,
            'package_id' => $item->package_id,
            'subscribable_type' => PushCampaign::class,
            'subscribable_id' => $campaign->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'renews_at' => $item->billing_model === 'monthly' ? $endsAt : null,
            'renewal_mode' => 'manual',
            'payment_id' => $payment->id,
        ]);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => PushCampaign::class,
            'entitled_id' => $campaign->id,
            'entitlement_code' => $item->package->entitlementCode(),
            'active_from' => $startsAt,
            'active_until' => $endsAt,
            'status' => 'active',
        ]);

        $campaign->update([
            'status' => $campaign->schedule_at && $campaign->schedule_at->isFuture() ? 'scheduled' : 'active',
            'published_at' => $campaign->published_at ?: now(),
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $endsAt,
        ]);
    }
}
