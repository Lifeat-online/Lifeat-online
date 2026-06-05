<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Entitlement;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Package;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PayFastCheckoutService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionRenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_extend_reactivates_subscription_entitlement_and_listing_state(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Lifecycle Extend Listing',
            'slug' => 'lifecycle-extend-listing',
            'status' => 'draft',
        ]);
        $subscription = $this->subscriptionForListing($owner, $listing, [
            'status' => 'expired',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subDay(),
            'renews_at' => null,
        ]);
        $entitlement = $this->entitlementFor($subscription, $listing, [
            'status' => 'expired',
            'active_until' => now()->subDay(),
        ]);

        app(SubscriptionLifecycleService::class)->extend($subscription, 30);

        $subscription->refresh();
        $entitlement->refresh();
        $listing->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertSame('active', $entitlement->status);
        $this->assertTrue($subscription->ends_at->isFuture());
        $this->assertEquals($subscription->ends_at->toDateTimeString(), $entitlement->active_until->toDateTimeString());
        $this->assertSame('published', $listing->status);
        $this->assertSame($subscription->id, $listing->active_subscription_id);
    }

    public function test_suspend_deactivates_entitlement_and_listing_state(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Lifecycle Suspend Listing',
            'slug' => 'lifecycle-suspend-listing',
            'status' => 'published',
        ]);
        $subscription = $this->subscriptionForListing($owner, $listing);
        $entitlement = $this->entitlementFor($subscription, $listing);
        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        app(SubscriptionLifecycleService::class)->suspend($subscription, 'Manual risk review.');

        $subscription->refresh();
        $entitlement->refresh();
        $listing->refresh();

        $this->assertSame('suspended', $subscription->status);
        $this->assertSame('suspended', $entitlement->status);
        $this->assertNull($subscription->renews_at);
        $this->assertSame('draft', $listing->status);
        $this->assertNull($listing->active_subscription_id);
    }

    public function test_expire_deactivates_entitlement_and_listing_state(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Lifecycle Expire Listing',
            'slug' => 'lifecycle-expire-listing',
            'status' => 'published',
        ]);
        $subscription = $this->subscriptionForListing($owner, $listing, [
            'ends_at' => now()->subHour(),
            'renews_at' => now()->subHour(),
        ]);
        $entitlement = $this->entitlementFor($subscription, $listing);
        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        app(SubscriptionLifecycleService::class)->expire($subscription);

        $subscription->refresh();
        $entitlement->refresh();
        $listing->refresh();

        $this->assertSame('expired', $subscription->status);
        $this->assertSame('expired', $entitlement->status);
        $this->assertNull($subscription->renews_at);
        $this->assertSame('draft', $listing->status);
        $this->assertNull($listing->active_subscription_id);
    }

    public function test_renewal_order_creation_is_idempotent_for_existing_pending_order(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Lifecycle Renewal Listing',
            'slug' => 'lifecycle-renewal-listing',
            'status' => 'published',
        ]);
        $subscription = $this->subscriptionForListing($owner, $listing, [
            'renewal_mode' => 'auto',
            'ends_at' => now()->addDay(),
            'renews_at' => now()->addDay(),
        ]);

        $service = app(SubscriptionRenewalService::class);
        $firstOrder = $service->createRenewalOrder($subscription);
        $secondOrder = $service->createRenewalOrder($subscription);

        $this->assertTrue($firstOrder->is($secondOrder));
        $this->assertSame(1, Order::where('renewed_subscription_id', $subscription->id)->count());
        $this->assertSame(1, $firstOrder->payments()->count());
        $this->assertSame(1, $firstOrder->items()->count());
        $this->assertSame(1, $firstOrder->invoices()->count());
    }

    public function test_paid_advert_renewal_reactivates_campaign_subscription_and_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = $this->listingWithActiveBusinessEntitlement($owner, 'advert-renewal-listing');
        $campaign = AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Advert Renewal Campaign',
            'slug' => 'advert-renewal-campaign',
            'headline' => 'Local renewal offer',
            'body' => 'A local advert package renewal test.',
            'placement' => 'banner',
            'status' => 'paused',
            'budget_currency' => 'ZAR',
        ]);
        $expiredSubscription = $this->subscriptionForPurchasable(
            $owner,
            Package::where('slug', 'advert-boost-30d')->firstOrFail(),
            AdCampaign::class,
            $campaign->id,
            [
                'status' => 'expired',
                'starts_at' => now()->subDays(60),
                'ends_at' => now()->subDay(),
                'renews_at' => now()->subDay(),
            ]
        );
        $this->entitlementForCampaign($expiredSubscription, $campaign, [
            'status' => 'expired',
            'active_until' => now()->subDay(),
        ]);
        $campaign->update([
            'active_subscription_id' => $expiredSubscription->id,
            'package_expires_at' => $expiredSubscription->ends_at,
        ]);

        $order = app(SubscriptionRenewalService::class)->createRenewalOrder($expiredSubscription);
        $this->markOrderPaidThroughPayFast($order, 'pf-advert-renewal');

        $campaign->refresh();
        $renewedSubscription = $campaign->activeSubscription()->firstOrFail();
        $this->assertNotSame($expiredSubscription->id, $renewedSubscription->id);
        $this->assertSame('active', $renewedSubscription->status);
        $this->assertSame('active', $campaign->status);
        $this->assertTrue($campaign->isOperational());
        $this->assertDatabaseHas('entitlements', [
            'subscription_id' => $renewedSubscription->id,
            'entitled_type' => AdCampaign::class,
            'entitled_id' => $campaign->id,
            'entitlement_code' => 'advert_package',
            'status' => 'active',
        ]);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('paid', $order->latestPayment()->fresh()->status);
    }

    public function test_late_failed_payfast_callback_does_not_roll_back_paid_advert_renewal(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = $this->listingWithActiveBusinessEntitlement($owner, 'advert-renewal-late-failure-listing');
        $campaign = AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Advert Renewal Late Failure',
            'slug' => 'advert-renewal-late-failure',
            'headline' => 'Local renewal survives late failure',
            'body' => 'A local advert package late-failure test.',
            'placement' => 'banner',
            'status' => 'paused',
            'budget_currency' => 'ZAR',
        ]);
        $expiredSubscription = $this->subscriptionForPurchasable(
            $owner,
            Package::where('slug', 'advert-boost-30d')->firstOrFail(),
            AdCampaign::class,
            $campaign->id,
            [
                'status' => 'expired',
                'starts_at' => now()->subDays(60),
                'ends_at' => now()->subDay(),
                'renews_at' => now()->subDay(),
            ]
        );
        $this->entitlementForCampaign($expiredSubscription, $campaign, [
            'status' => 'expired',
            'active_until' => now()->subDay(),
        ]);
        $campaign->update([
            'active_subscription_id' => $expiredSubscription->id,
            'package_expires_at' => $expiredSubscription->ends_at,
        ]);

        $order = app(SubscriptionRenewalService::class)->createRenewalOrder($expiredSubscription);
        $this->markOrderPaidThroughPayFast($order, 'pf-advert-renewal-late-failure');

        $failedPayload = [
            'order_number' => $order->order_number,
            'status' => 'failed',
            'provider_transaction_id' => 'pf-advert-renewal-late-failure',
            'failure_reason' => 'Gateway sent a late failure after completion.',
        ];
        $failedPayload['signature'] = app(PayFastCheckoutService::class)->generateSignature($failedPayload);

        $this->post(route('checkout.payfast.callback'), $failedPayload)
            ->assertOk()
            ->assertJson([
                'payment_status' => 'paid',
                'ignored' => 'Payment is already paid.',
            ]);

        $campaign->refresh();
        $renewedSubscription = $campaign->activeSubscription()->firstOrFail();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('paid', $order->latestPayment()->fresh()->status);
        $this->assertSame('active', $renewedSubscription->status);
        $this->assertSame('active', $campaign->status);
        $this->assertSame(1, Subscription::where('payment_id', $order->latestPayment()->id)->count());
        $this->assertDatabaseHas('entitlements', [
            'subscription_id' => $renewedSubscription->id,
            'entitled_type' => AdCampaign::class,
            'entitled_id' => $campaign->id,
            'entitlement_code' => 'advert_package',
            'status' => 'active',
        ]);
    }

    public function test_paid_push_renewal_reactivates_campaign_subscription_and_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = $this->listingWithActiveBusinessEntitlement($owner, 'push-renewal-listing');
        $campaign = PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Push Renewal Campaign',
            'slug' => 'push-renewal-campaign',
            'headline' => 'Local push renewal',
            'message' => 'A local push package renewal test.',
            'schedule_at' => now()->addDay(),
            'audience_scope' => 'listing_city',
            'status' => 'draft',
            'budget_currency' => 'ZAR',
        ]);
        $expiredSubscription = $this->subscriptionForPurchasable(
            $owner,
            Package::where('slug', 'push-campaign-once')->firstOrFail(),
            PushCampaign::class,
            $campaign->id,
            [
                'status' => 'expired',
                'starts_at' => now()->subDays(14),
                'ends_at' => now()->subDay(),
                'renews_at' => now()->subDay(),
            ]
        );
        $this->entitlementForCampaign($expiredSubscription, $campaign, [
            'status' => 'expired',
            'active_until' => now()->subDay(),
        ]);
        $campaign->update([
            'active_subscription_id' => $expiredSubscription->id,
            'package_expires_at' => $expiredSubscription->ends_at,
        ]);

        $order = app(SubscriptionRenewalService::class)->createRenewalOrder($expiredSubscription);
        $this->markOrderPaidThroughPayFast($order, 'pf-push-renewal');

        $campaign->refresh();
        $renewedSubscription = $campaign->activeSubscription()->firstOrFail();
        $this->assertNotSame($expiredSubscription->id, $renewedSubscription->id);
        $this->assertSame('active', $renewedSubscription->status);
        $this->assertSame('scheduled', $campaign->status);
        $this->assertTrue($campaign->isOperational());
        $this->assertDatabaseHas('entitlements', [
            'subscription_id' => $renewedSubscription->id,
            'entitled_type' => PushCampaign::class,
            'entitled_id' => $campaign->id,
            'entitlement_code' => 'push_notification',
            'status' => 'active',
        ]);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('paid', $order->latestPayment()->fresh()->status);
    }

    private function listingWithActiveBusinessEntitlement(User $owner, string $slug): Listing
    {
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => str_replace('-', ' ', $slug),
            'slug' => $slug,
            'status' => 'published',
        ]);
        $subscription = $this->subscriptionForListing($owner, $listing);
        $this->entitlementFor($subscription, $listing);
        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        return $listing;
    }

    private function subscriptionForPurchasable(User $owner, Package $package, string $subscribableType, int $subscribableId, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => $subscribableType,
            'subscribable_id' => $subscribableId,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ], $overrides));
    }

    private function entitlementForCampaign(Subscription $subscription, AdCampaign|PushCampaign $campaign, array $overrides = []): Entitlement
    {
        return Entitlement::create(array_merge([
            'subscription_id' => $subscription->id,
            'entitled_type' => $campaign::class,
            'entitled_id' => $campaign->id,
            'entitlement_code' => $subscription->package->entitlementCode(),
            'active_from' => $subscription->starts_at,
            'active_until' => $subscription->ends_at,
            'status' => 'active',
        ], $overrides));
    }

    private function markOrderPaidThroughPayFast(Order $order, string $providerTransactionId): void
    {
        $payload = [
            'order_number' => $order->order_number,
            'status' => 'paid',
            'provider_transaction_id' => $providerTransactionId,
            'amount_gross' => number_format((float) $order->total, 2, '.', ''),
            'currency' => $order->currency,
        ];
        $payload['signature'] = app(PayFastCheckoutService::class)->generateSignature($payload);

        $this->post(route('checkout.payfast.callback'), $payload)->assertOk();
    }

    private function subscriptionForListing(User $owner, Listing $listing, array $overrides = []): Subscription
    {
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();

        return Subscription::create(array_merge([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ], $overrides));
    }

    private function entitlementFor(Subscription $subscription, Listing $listing, array $overrides = []): Entitlement
    {
        return Entitlement::create(array_merge([
            'subscription_id' => $subscription->id,
            'entitled_type' => Listing::class,
            'entitled_id' => $listing->id,
            'entitlement_code' => 'business_directory',
            'active_from' => $subscription->starts_at,
            'active_until' => $subscription->ends_at,
            'status' => 'active',
        ], $overrides));
    }
}
