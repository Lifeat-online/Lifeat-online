<?php

namespace App\Listeners;

use App\Events\PaymentPaid;
use App\Events\PayoutPaid;
use App\Events\PushCampaignDispatched;
use App\Events\SubscriptionActivated;
use App\Support\Logging\OperationalLog;

class RecordRevenueLifecycleEvent
{
    public function handle(object $event): void
    {
        match (true) {
            $event instanceof PaymentPaid => $this->recordPaymentPaid($event),
            $event instanceof SubscriptionActivated => $this->recordSubscriptionActivated($event),
            $event instanceof PayoutPaid => $this->recordPayoutPaid($event),
            $event instanceof PushCampaignDispatched => $this->recordPushCampaignDispatched($event),
            default => null,
        };
    }

    private function recordPaymentPaid(PaymentPaid $event): void
    {
        $payment = $event->payment;

        OperationalLog::info('domain.payment_paid', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'user_id' => $payment->user_id,
            'provider' => $payment->provider,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'provider_transaction_id' => $payment->provider_transaction_id,
            'paid_at' => $payment->paid_at,
        ]);
    }

    private function recordSubscriptionActivated(SubscriptionActivated $event): void
    {
        $subscription = $event->subscription;

        OperationalLog::info('domain.subscription_activated', [
            'subscription_id' => $subscription->id,
            'payment_id' => $event->payment->id,
            'user_id' => $subscription->user_id,
            'package_id' => $subscription->package_id,
            'subscribable_type' => $subscription->subscribable_type,
            'subscribable_id' => $subscription->subscribable_id,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'renews_at' => $subscription->renews_at,
            'status' => $subscription->status,
        ]);
    }

    private function recordPayoutPaid(PayoutPaid $event): void
    {
        $payout = $event->payoutRequest;

        OperationalLog::info('domain.payout_paid', [
            'payout_request_id' => $payout->id,
            'wallet_id' => $payout->wallet_id,
            'requested_by_user_id' => $payout->requested_by_user_id,
            'amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'payment_reference' => $payout->payment_reference,
            'paid_at' => $payout->paid_at,
            'wallet_ledger_entry_id' => $event->ledgerEntry?->id,
        ]);
    }

    private function recordPushCampaignDispatched(PushCampaignDispatched $event): void
    {
        $campaign = $event->pushCampaign;

        OperationalLog::info('domain.push_campaign_dispatched', [
            'push_campaign_id' => $campaign->id,
            'notification_log_id' => $event->notificationLog->id,
            'listing_id' => $campaign->listing_id,
            'event_id' => $campaign->event_id,
            'user_id' => $campaign->user_id,
            'status' => $campaign->status,
            'sent_at' => $campaign->sent_at,
            'active_subscription_id' => $campaign->active_subscription_id,
        ]);
    }
}
