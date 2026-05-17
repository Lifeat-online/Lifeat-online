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

class AdminCampaignCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_ad_campaign_for_listing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Campaign Business',
            'status' => 'published',
        ]);
        $event = Event::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Campaign Launch',
            'slug' => 'campaign-launch-'.Str::lower(Str::random(6)),
            'start_at' => now()->addWeek(),
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.campaigns.ads.create'))
            ->assertOk()
            ->assertSee('Add Ad Campaign')
            ->assertSee('Campaign Business');

        $response = $this->actingAs($admin)
            ->post(route('admin.campaigns.ads.store'), [
                'listing_id' => $listing->id,
                'event_id' => $event->id,
                'title' => 'Admin Added Advert',
                'headline' => 'Local launch offer',
                'body' => 'Advert body copy',
                'destination_url' => 'https://example.test/offer',
                'placement' => 'banner',
                'status' => 'ready',
                'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'end_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ]);

        $campaign = AdCampaign::where('title', 'Admin Added Advert')->firstOrFail();

        $response->assertRedirect(route('admin.campaigns.ads.show', $campaign));
        $this->assertSame($listing->id, $campaign->listing_id);
        $this->assertSame($owner->id, $campaign->user_id);
        $this->assertSame($event->id, $campaign->event_id);
        $this->assertSame('ready', $campaign->status);
    }

    public function test_admin_can_create_push_campaign_for_listing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.campaigns.push.create'))
            ->assertOk()
            ->assertSee('Add Push Campaign')
            ->assertSee($listing->title);

        $response = $this->actingAs($admin)
            ->post(route('admin.campaigns.push.store'), [
                'listing_id' => $listing->id,
                'title' => 'Admin Added Push',
                'headline' => 'Tonight only',
                'message' => 'A short push message for subscribers.',
                'schedule_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'audience_scope' => 'listing_city',
                'target_city' => 'Bethlehem',
                'target_region' => 'Free State',
                'status' => 'scheduled',
            ]);

        $campaign = PushCampaign::where('title', 'Admin Added Push')->firstOrFail();

        $response->assertRedirect(route('admin.campaigns.push.show', $campaign));
        $this->assertSame($listing->id, $campaign->listing_id);
        $this->assertSame($owner->id, $campaign->user_id);
        $this->assertSame('scheduled', $campaign->status);
        $this->assertSame('listing_city', $campaign->audience_scope);
    }
}
