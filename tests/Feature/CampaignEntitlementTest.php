<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Entitlement;
use App\Models\Listing;
use App\Models\Package;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PushCampaignDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CampaignEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_approve_advert_without_active_listing_entitlement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'draft',
        ]);
        $campaign = $this->adCampaignFor($owner, $listing);
        $this->attachCampaignSubscription($campaign, 'advert-boost-30d');

        $this->actingAs($admin)
            ->post(route('admin.campaigns.ads.approve', $campaign))
            ->assertStatus(422);

        $this->assertSame('ready', $campaign->fresh()->status);
    }

    public function test_admin_cannot_approve_advert_without_active_advert_entitlement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $this->attachListingSubscription($owner, $listing);
        $campaign = $this->adCampaignFor($owner, $listing);

        $this->actingAs($admin)
            ->post(route('admin.campaigns.ads.approve', $campaign))
            ->assertStatus(422);

        $this->assertSame('ready', $campaign->fresh()->status);
    }

    public function test_admin_can_approve_advert_when_listing_and_advert_entitlements_are_active(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $this->attachListingSubscription($owner, $listing);
        $campaign = $this->adCampaignFor($owner, $listing);
        $this->attachCampaignSubscription($campaign, 'advert-boost-30d');

        $this->actingAs($admin)
            ->post(route('admin.campaigns.ads.approve', $campaign))
            ->assertRedirect(route('admin.campaigns.ads.show', $campaign));

        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertTrue($campaign->fresh()->isOperational());
    }

    public function test_push_dispatch_requires_active_listing_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'draft',
        ]);
        $campaign = $this->pushCampaignFor($owner, $listing);
        $this->attachCampaignSubscription($campaign, 'push-campaign-once');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('linked business listing needs an active package');

        app(PushCampaignDispatchService::class)->dispatch($campaign);
    }

    public function test_push_dispatch_requires_active_push_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $this->attachListingSubscription($owner, $listing);
        $campaign = $this->pushCampaignFor($owner, $listing);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('needs an active push package');

        app(PushCampaignDispatchService::class)->dispatch($campaign);
    }

    private function adCampaignFor(User $owner, Listing $listing): AdCampaign
    {
        return AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Entitled Advert',
            'slug' => 'entitled-advert-'.fake()->unique()->bothify('????'),
            'headline' => 'Local offer',
            'body' => 'Campaign body',
            'placement' => 'banner',
            'status' => 'ready',
            'budget_currency' => 'ZAR',
        ]);
    }

    private function pushCampaignFor(User $owner, Listing $listing): PushCampaign
    {
        return PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Entitled Push',
            'slug' => 'entitled-push-'.fake()->unique()->bothify('????'),
            'headline' => 'Local update',
            'message' => 'Campaign message',
            'schedule_at' => now()->subMinute(),
            'audience_scope' => 'listing_city',
            'status' => 'active',
            'budget_currency' => 'ZAR',
        ]);
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

    private function attachCampaignSubscription(AdCampaign|PushCampaign $campaign, string $packageSlug): Subscription
    {
        $package = Package::where('slug', $packageSlug)->firstOrFail();
        $subscription = $this->subscriptionFor($campaign->owner, $package, $campaign::class, $campaign->id);

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
