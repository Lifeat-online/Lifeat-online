<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
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
