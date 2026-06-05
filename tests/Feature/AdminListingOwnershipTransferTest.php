<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Listing;
use App\Models\PushCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminListingOwnershipTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_transfer_listing_owner_with_cascaded_campaign_ownership_and_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $oldOwner = User::factory()->create(['role' => 'business_owner']);
        $newOwner = User::factory()->create(['role' => 'business_owner']);

        $listing = Listing::factory()->create([
            'user_id' => $oldOwner->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $event = Event::create([
            'user_id' => $oldOwner->id,
            'listing_id' => $listing->id,
            'title' => 'Owner Transfer Event',
            'slug' => 'owner-transfer-event-'.Str::lower(Str::random(6)),
            'excerpt' => null,
            'description' => null,
            'venue_name' => null,
            'address_line' => null,
            'city' => null,
            'region' => null,
            'country' => null,
            'postal_code' => null,
            'start_at' => now()->addWeek(),
            'end_at' => null,
            'website_url' => null,
            'featured_image' => null,
            'status' => 'draft',
            'published_at' => null,
            'is_all_day' => false,
        ]);

        $adCampaign = AdCampaign::create([
            'listing_id' => $listing->id,
            'event_id' => null,
            'user_id' => $oldOwner->id,
            'title' => 'Owner Transfer Advert',
            'slug' => 'owner-transfer-advert-'.Str::lower(Str::random(6)),
            'headline' => 'Original owner advert',
            'body' => 'Advert body',
            'destination_url' => null,
            'creative_image' => null,
            'placement' => 'directory_card',
            'budget_amount' => 100,
            'budget_currency' => 'ZAR',
            'targeting_json' => [],
            'popup_settings_json' => [],
            'start_at' => now(),
            'end_at' => now()->addWeek(),
            'status' => 'ready',
            'published_at' => null,
        ]);

        $pushCampaign = PushCampaign::create([
            'listing_id' => $listing->id,
            'event_id' => null,
            'user_id' => $oldOwner->id,
            'title' => 'Owner Transfer Push',
            'slug' => 'owner-transfer-push-'.Str::lower(Str::random(6)),
            'headline' => 'Original owner push',
            'message' => 'Push body',
            'budget_amount' => 100,
            'budget_currency' => 'ZAR',
            'schedule_at' => now()->addDay(),
            'audience_scope' => 'listing_city',
            'target_city' => null,
            'target_region' => null,
            'radius_km' => null,
            'status' => 'draft',
            'published_at' => null,
            'sent_at' => null,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.listings.update', $listing), $this->payload($listing, [
            'owner_user_id' => $newOwner->id,
        ]));

        $response->assertRedirect(route('admin.listings.edit', $listing->fresh()));

        $this->assertSame($newOwner->id, $listing->fresh()->user_id);
        $this->assertSame($newOwner->id, $event->fresh()->user_id);
        $this->assertSame($newOwner->id, $adCampaign->fresh()->user_id);
        $this->assertSame($newOwner->id, $pushCampaign->fresh()->user_id);

        $audit = AuditLog::where('action', 'listing.ownership_transferred')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame(Listing::class, $audit->subject_type);
        $this->assertSame($listing->id, $audit->subject_id);
        $this->assertSame($oldOwner->id, $audit->before_json['user_id']);
        $this->assertSame($oldOwner->email, $audit->before_json['owner_email']);
        $this->assertSame($newOwner->id, $audit->after_json['user_id']);
        $this->assertSame($newOwner->email, $audit->after_json['owner_email']);
    }

    public function test_staff_listing_update_cannot_transfer_owner(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $oldOwner = User::factory()->create(['role' => 'business_owner']);
        $newOwner = User::factory()->create(['role' => 'business_owner']);

        $listing = Listing::factory()->create([
            'user_id' => $oldOwner->id,
            'registered_by_user_id' => $staff->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->actingAs($staff)->put(route('admin.listings.update', $listing), $this->payload($listing, [
            'owner_user_id' => $newOwner->id,
        ]));

        $response->assertRedirect(route('admin.listings.edit', $listing->fresh()));

        $this->assertSame($oldOwner->id, $listing->fresh()->user_id);
        $this->assertSame(0, AuditLog::where('action', 'listing.ownership_transferred')->count());
    }

    public function test_staff_listing_form_does_not_show_owner_transfer_control(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'registered_by_user_id' => $staff->id,
            'status' => 'draft',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.listings.edit', $listing))
            ->assertOk()
            ->assertDontSee('name="owner_user_id"', false);
    }

    private function payload(Listing $listing, array $overrides = []): array
    {
        return array_merge([
            'title' => $listing->title,
            'slug' => $listing->slug,
            'excerpt' => $listing->excerpt,
            'description' => $listing->description,
            'website_url' => $listing->website_url,
            'email' => $listing->email,
            'phone' => $listing->phone,
            'address_line' => $listing->address_line,
            'city' => $listing->city,
            'region' => $listing->region,
            'country' => $listing->country,
            'postal_code' => $listing->postal_code,
            'status' => $listing->status ?: 'draft',
            'published_at' => null,
            'category_ids' => [],
        ], $overrides);
    }
}
