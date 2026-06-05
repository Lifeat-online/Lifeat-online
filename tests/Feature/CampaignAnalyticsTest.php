<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\CampaignTrackingEvent;
use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\PushCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ad_tracking_events_are_logged_and_visible_in_admin_reporting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $campaign = AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Tracked Advert',
            'slug' => 'tracked-advert',
            'headline' => 'Local offer',
            'body' => 'Campaign body',
            'destination_url' => 'https://example.test/offer',
            'placement' => 'banner',
            'status' => 'active',
            'budget_currency' => 'ZAR',
            'published_at' => now(),
        ]);

        $this->withHeader('referer', 'https://life.test/home')
            ->get(route('ad-tracking.impression', $campaign))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->get(route('ad-tracking.impression', $campaign))->assertOk();

        $this->withHeader('referer', 'https://life.test/directory')
            ->get(route('ad-tracking.click', $campaign))
            ->assertRedirect('https://example.test/offer');

        $campaign->refresh();

        $this->assertSame(2, $campaign->impressions);
        $this->assertSame(1, $campaign->clicks);
        $this->assertDatabaseCount('campaign_tracking_events', 3);
        $this->assertDatabaseHas('campaign_tracking_events', [
            'trackable_type' => $campaign->getMorphClass(),
            'trackable_id' => $campaign->id,
            'event_type' => CampaignTrackingEvent::TYPE_CLICK,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.campaigns.ads.show', $campaign))
            ->assertOk()
            ->assertSee('Daily Performance')
            ->assertSee('Recent Tracking Events')
            ->assertSee('2')
            ->assertSee('1');

        $this->actingAs($admin)
            ->get(route('admin.campaigns.ads.index', ['sort' => 'clicks']))
            ->assertOk()
            ->assertSee('Tracked Advert')
            ->assertSee('2 impressions')
            ->assertSee('1 clicks');
    }

    public function test_push_open_tracking_is_tokenized_and_visible_in_admin_reporting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'city' => 'Bethlehem',
            'status' => 'published',
        ]);
        $campaign = PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Tracked Push',
            'slug' => 'tracked-push',
            'headline' => 'Tonight only',
            'message' => 'A short push message for subscribers.',
            'audience_scope' => 'listing_city',
            'target_city' => 'Bethlehem',
            'status' => 'active',
            'budget_currency' => 'ZAR',
            'sent_at' => now(),
        ]);

        NotificationLog::create([
            'channel' => 'push',
            'notification_type' => 'push_campaign',
            'notifiable_type' => PushCampaign::class,
            'notifiable_id' => $campaign->id,
            'recipient' => 'Bethlehem subscribers',
            'status' => 'sent',
            'sent_at' => now(),
            'meta_json' => ['campaign_id' => $campaign->id],
        ]);

        $this->get(route('ad-tracking.push-open', [$campaign, 't' => 'subscriber-1']))->assertOk();
        $this->get(route('ad-tracking.push-open', [$campaign, 't' => 'subscriber-1']))->assertOk();
        $this->get(route('ad-tracking.push-open', [$campaign, 't' => 'subscriber-2']))->assertOk();

        $campaign->refresh();

        $this->assertSame(2, $campaign->open_count);
        $this->assertDatabaseCount('campaign_tracking_events', 2);
        $this->assertDatabaseHas('campaign_tracking_events', [
            'trackable_type' => $campaign->getMorphClass(),
            'trackable_id' => $campaign->id,
            'event_type' => CampaignTrackingEvent::TYPE_PUSH_OPEN,
            'tracking_token' => 'subscriber-1',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.campaigns.push.show', $campaign))
            ->assertOk()
            ->assertSee('Daily Open Activity')
            ->assertSee('Recent Open Events')
            ->assertSee('subscriber-1')
            ->assertSee('2');

        $this->actingAs($admin)
            ->get(route('admin.campaigns.push.index', ['sort' => 'opens']))
            ->assertOk()
            ->assertSee('Tracked Push')
            ->assertSee('1 deliveries')
            ->assertSee('2 opens');
    }

    public function test_admin_campaign_report_aggregates_ads_push_and_daily_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$adCampaign, $pushCampaign] = $this->seedReportCampaigns();

        $this->get(route('ad-tracking.impression', $adCampaign))->assertOk();
        $this->get(route('ad-tracking.impression', $adCampaign))->assertOk();
        $this->get(route('ad-tracking.impression', $adCampaign))->assertOk();
        $this->get(route('ad-tracking.click', $adCampaign))->assertRedirect('https://example.test/offer');

        NotificationLog::create([
            'channel' => 'push',
            'notification_type' => 'push_campaign',
            'notifiable_type' => PushCampaign::class,
            'notifiable_id' => $pushCampaign->id,
            'recipient' => 'Bethlehem subscriber 1',
            'status' => 'sent',
            'sent_at' => now(),
            'meta_json' => ['campaign_id' => $pushCampaign->id],
        ]);
        NotificationLog::create([
            'channel' => 'push',
            'notification_type' => 'push_campaign',
            'notifiable_type' => PushCampaign::class,
            'notifiable_id' => $pushCampaign->id,
            'recipient' => 'Bethlehem subscriber 2',
            'status' => 'sent',
            'sent_at' => now(),
            'meta_json' => ['campaign_id' => $pushCampaign->id],
        ]);

        $this->get(route('ad-tracking.push-open', [$pushCampaign, 't' => 'report-open-1']))->assertOk();
        $this->get(route('ad-tracking.push-open', [$pushCampaign, 't' => 'report-open-2']))->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.campaigns.report', [
                'from' => now()->subDay()->toDateString(),
                'to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Campaign Report')
            ->assertSee('Daily Trend')
            ->assertSee('Top Ad Campaigns')
            ->assertSee('Tracked Report Advert')
            ->assertSee('Tracked Report Push')
            ->assertSee('3')
            ->assertSee('100%');
    }

    public function test_admin_can_export_campaign_report_csv_datasets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$adCampaign, $pushCampaign] = $this->seedReportCampaigns();

        $this->get(route('ad-tracking.impression', $adCampaign))->assertOk();
        $this->get(route('ad-tracking.click', $adCampaign))->assertRedirect('https://example.test/offer');
        $this->get(route('ad-tracking.push-open', [$pushCampaign, 't' => 'csv-open-1']))->assertOk();

        $adExport = $this->actingAs($admin)->get(route('admin.campaigns.report.export', [
            'dataset' => 'ad-summary',
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]));
        $adExport->assertOk();
        $adExport->assertHeader('content-disposition');
        $this->assertStringContainsString('Tracked Report Advert', $adExport->streamedContent());

        $eventsExport = $this->actingAs($admin)->get(route('admin.campaigns.report.export', [
            'dataset' => 'tracking-events',
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]));
        $eventsExport->assertOk();
        $this->assertStringContainsString('push_open', $eventsExport->streamedContent());
    }

    public function test_campaign_report_and_exports_are_limited_to_admin_and_editor_roles(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $support = User::factory()->create(['role' => 'support']);
        $staff = User::factory()->create(['role' => 'staff']);
        $reportRoute = route('admin.campaigns.report');
        $exportRoute = route('admin.campaigns.report.export', ['dataset' => 'ad-summary']);

        $this->actingAs($editor)
            ->get($reportRoute)
            ->assertOk()
            ->assertSee('Campaign Report');

        $this->actingAs($editor)
            ->get($exportRoute)
            ->assertOk()
            ->assertHeader('content-disposition');

        foreach ([$support, $staff] as $user) {
            $this->actingAs($user)
                ->get($reportRoute)
                ->assertForbidden();

            $this->actingAs($user)
                ->get($exportRoute)
                ->assertForbidden();
        }
    }

    private function seedReportCampaigns(): array
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'city' => 'Bethlehem',
            'status' => 'published',
        ]);

        $adCampaign = AdCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Tracked Report Advert',
            'slug' => 'tracked-report-advert',
            'headline' => 'Report local offer',
            'body' => 'Campaign body',
            'destination_url' => 'https://example.test/offer',
            'placement' => 'banner',
            'status' => 'active',
            'budget_currency' => 'ZAR',
            'published_at' => now(),
        ]);

        $pushCampaign = PushCampaign::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Tracked Report Push',
            'slug' => 'tracked-report-push',
            'headline' => 'Report push',
            'message' => 'A short push message for report subscribers.',
            'audience_scope' => 'listing_city',
            'target_city' => 'Bethlehem',
            'status' => 'active',
            'budget_currency' => 'ZAR',
            'sent_at' => now(),
        ]);

        return [$adCampaign, $pushCampaign];
    }
}
