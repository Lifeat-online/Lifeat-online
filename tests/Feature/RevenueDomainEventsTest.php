<?php

namespace Tests\Feature;

use App\Events\PaymentPaid;
use App\Events\PayoutPaid;
use App\Events\PushCampaignDispatched;
use App\Events\SubscriptionActivated;
use App\Models\Entitlement;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PushCampaign;
use App\Models\StaffWallet;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PushCampaignDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class RevenueDomainEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_paid_dispatches_payment_and_subscription_events(): void
    {
        EventFacade::fake([
            PaymentPaid::class,
            SubscriptionActivated::class,
        ]);

        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'draft',
            'source_channel' => 'staff_assisted',
        ]);
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'ORD-DOMAIN-1',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'package_id' => $package->id,
            'purchasable_type' => Listing::class,
            'purchasable_id' => $listing->id,
            'name_snapshot' => $package->name,
            'unit_price' => 500,
            'quantity' => 1,
            'billing_model' => 'six_monthly',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 500,
            'currency' => 'ZAR',
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_transaction_id' => 'DOMAIN-TXN-1',
        ]);

        $subscription = Subscription::firstOrFail();

        EventFacade::assertDispatched(PaymentPaid::class, fn (PaymentPaid $event): bool =>
            $event->payment->is($payment)
            && $event->payment->status === 'paid'
        );

        EventFacade::assertDispatched(SubscriptionActivated::class, fn (SubscriptionActivated $event): bool =>
            $event->subscription->is($subscription)
            && $event->payment->is($payment)
            && $event->subscription->subscribable_type === Listing::class
            && $event->subscription->subscribable_id === $listing->id
        );
    }

    public function test_marking_payout_paid_dispatches_payout_event_with_ledger_entry(): void
    {
        EventFacade::fake([PayoutPaid::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 300,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);
        $payout = PayoutRequest::create([
            'wallet_id' => $wallet->id,
            'requested_by_user_id' => $staff->id,
            'amount' => 120,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_APPROVED,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.payout-requests.mark-paid', $payout), [
            'payment_reference' => 'BANK-DOMAIN-123',
        ])->assertRedirect(route('admin.payout-requests.show', $payout));

        $ledgerEntry = WalletLedgerEntry::where('payout_request_id', $payout->id)->firstOrFail();

        EventFacade::assertDispatched(PayoutPaid::class, fn (PayoutPaid $event): bool =>
            $event->payoutRequest->is($payout)
            && $event->payoutRequest->status === PayoutRequest::STATUS_PAID
            && $event->ledgerEntry?->is($ledgerEntry)
        );
    }

    public function test_push_campaign_dispatch_emits_domain_event(): void
    {
        EventFacade::fake([PushCampaignDispatched::class]);

        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $this->attachListingSubscription($owner, $listing);

        $campaign = PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Domain Push',
            'slug' => 'domain-push',
            'headline' => 'Domain update',
            'message' => 'Campaign message.',
            'schedule_at' => now()->subMinute(),
            'audience_scope' => 'listing_city',
            'status' => 'active',
            'budget_currency' => 'ZAR',
        ]);
        $this->attachPushSubscription($owner, $campaign);

        $notification = app(PushCampaignDispatchService::class)->dispatch($campaign);

        EventFacade::assertDispatched(PushCampaignDispatched::class, fn (PushCampaignDispatched $event): bool =>
            $event->pushCampaign->is($campaign)
            && $event->notificationLog->is($notification)
            && $event->pushCampaign->sent_at !== null
        );
    }

    private function attachListingSubscription(User $owner, Listing $listing): Subscription
    {
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $subscription = $this->subscriptionFor($owner, $package, Listing::class, $listing->id);

        Entitlement::create([
            'subscription_id' => $subscription->id,
            'entitled_type' => Listing::class,
            'entitled_id' => $listing->id,
            'entitlement_code' => 'business_directory',
            'active_from' => now()->subDay(),
            'active_until' => now()->addMonth(),
            'status' => 'active',
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        return $subscription;
    }

    private function attachPushSubscription(User $owner, PushCampaign $campaign): Subscription
    {
        $subscription = $this->subscriptionFor(
            $owner,
            Package::where('slug', 'push-campaign-once')->firstOrFail(),
            PushCampaign::class,
            $campaign->id
        );

        $campaign->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        return $subscription;
    }

    private function subscriptionFor(User $owner, Package $package, string $subscribableType, int $subscribableId): Subscription
    {
        return Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => $subscribableType,
            'subscribable_id' => $subscribableId,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);
    }
}
