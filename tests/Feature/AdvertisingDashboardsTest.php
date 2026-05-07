<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Listing;
use App\Models\PushCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvertisingDashboardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_advertising_summary_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $owner->id, 'status' => 'published']);

        $campaign = AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Test Ad',
            'slug' => 'test-ad-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'placement' => 'banner',
            'budget_currency' => 'ZAR',
            'impressions' => 10,
            'clicks' => 2,
        ]);

        $this->actingAs($owner)
            ->getJson(route('api.client.advertising.summary', $listing))
            ->assertOk()
            ->assertJsonPath('listing.id', $listing->id)
            ->assertJsonPath('ad_campaigns.0.id', $campaign->id);

        $this->actingAs($other)
            ->getJson(route('api.client.advertising.summary', $listing))
            ->assertForbidden();
    }

    public function test_staff_can_update_assigned_campaign_with_conflict_detection(): void
    {
        $staff = User::factory()->create(['role' => 'sales_staff']);
        $owner = User::factory()->create();
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'registered_by_user_id' => $staff->id,
            'status' => 'published',
        ]);

        $ad = AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Staff Ad',
            'slug' => 'staff-ad-'.Str::lower(Str::random(6)),
            'status' => 'draft',
            'placement' => 'banner',
            'budget_currency' => 'ZAR',
        ]);

        $bad = $this->actingAs($staff)
            ->putJson(route('api.staff.advertising.ad-campaigns.update', $ad), [
                'expected_updated_at' => now()->subDay()->toIso8601String(),
                'status' => 'active',
                'placement' => 'popup',
                'budget_amount' => 100,
                'budget_currency' => 'ZAR',
                'start_at' => null,
                'end_at' => null,
                'targeting' => ['city' => 'Bethlehem'],
                'popup_settings' => ['frequency' => 'once_per_day'],
            ]);

        $bad->assertStatus(409);

        $ok = $this->actingAs($staff)
            ->putJson(route('api.staff.advertising.ad-campaigns.update', $ad->fresh()), [
                'expected_updated_at' => $ad->fresh()->updated_at->toIso8601String(),
                'status' => 'active',
                'placement' => 'popup',
                'budget_amount' => 100,
                'budget_currency' => 'ZAR',
                'start_at' => null,
                'end_at' => null,
                'targeting' => ['city' => 'Bethlehem'],
                'popup_settings' => ['frequency' => 'once_per_day'],
            ]);

        $ok->assertOk();
        $this->assertSame('active', $ad->fresh()->status);
        $this->assertSame('popup', $ad->fresh()->placement);
    }

    public function test_staff_summary_requires_assignment(): void
    {
        $staff = User::factory()->create(['role' => 'sales_staff']);
        $otherStaff = User::factory()->create(['role' => 'sales_staff']);
        $owner = User::factory()->create();
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'registered_by_user_id' => $staff->id,
            'status' => 'published',
        ]);

        PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Push',
            'slug' => 'push-'.Str::lower(Str::random(6)),
            'message' => 'Hello',
            'status' => 'draft',
            'budget_currency' => 'ZAR',
        ]);

        $this->actingAs($staff)
            ->getJson(route('api.staff.advertising.summary', $listing))
            ->assertOk()
            ->assertJsonPath('listing.id', $listing->id);

        $this->actingAs($otherStaff)
            ->getJson(route('api.staff.advertising.summary', $listing))
            ->assertForbidden();
    }

    public function test_staff_can_open_assigned_listing_workspace_and_manage_resources(): void
    {
        $staff = User::factory()->create(['role' => 'sales_staff']);
        $otherStaff = User::factory()->create(['role' => 'sales_staff']);
        $owner = User::factory()->create();

        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'registered_by_user_id' => $staff->id,
            'status' => 'published',
        ]);

        $event = Event::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Test Event',
            'slug' => 'test-event-'.Str::lower(Str::random(6)),
            'start_at' => now()->addDay(),
            'status' => 'draft',
        ]);

        $ad = AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Test Ad',
            'slug' => 'test-ad-'.Str::lower(Str::random(6)),
            'status' => 'draft',
            'placement' => 'banner',
            'budget_currency' => 'ZAR',
        ]);

        $push = PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Test Push',
            'slug' => 'test-push-'.Str::lower(Str::random(6)),
            'message' => 'Hello',
            'status' => 'draft',
            'budget_currency' => 'ZAR',
        ]);

        $this->actingAs($staff)
            ->get(route('account.listings.show', $listing))
            ->assertOk();

        $this->actingAs($otherStaff)
            ->get(route('account.listings.show', $listing))
            ->assertForbidden();

        $this->actingAs($staff)
            ->delete(route('account.listings.events.destroy', [$listing, $event]))
            ->assertRedirect(route('account.listings.events.index', $listing));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);

        $this->actingAs($staff)
            ->delete(route('account.listings.ad-campaigns.destroy', [$listing, $ad]))
            ->assertRedirect(route('account.listings.ad-campaigns.index', $listing));

        $this->assertDatabaseMissing('ad_campaigns', ['id' => $ad->id]);

        $this->actingAs($staff)
            ->delete(route('account.listings.push-campaigns.destroy', [$listing, $push]))
            ->assertRedirect(route('account.listings.push-campaigns.index', $listing));

        $this->assertDatabaseMissing('push_campaigns', ['id' => $push->id]);
    }
}
