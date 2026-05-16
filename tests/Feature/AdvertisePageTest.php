<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PushCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvertisePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertise_page_renders_monetisation_ladder_and_live_package_cards(): void
    {
        $response = $this->get(route('advertise.index'));

        $response->assertOk();
        $response->assertSee('Put your business on the platform that is built to employ local people.');
        $response->assertSee('Why self-service costs more');
        $response->assertSee('Staff assisted is cheaper because it creates a job.');
        $response->assertSee('Business Directory Standard');
        $response->assertSee('Business Directory Self-Service');
        $response->assertSee('Event One-Off Package');
        $response->assertSee('Sitewide Banner Placement');
        $response->assertSee('Push notification');
    }

    public function test_authenticated_user_can_create_advertising_bundle_order(): void
    {
        $user = User::factory()->create(['role' => 'business_owner']);

        $response = $this->actingAs($user)->post(route('advertise.start'), [
            'business_name' => 'Bundle Bakery',
            'city' => 'Bethlehem',
            'listing_package_slug' => 'business-directory-self-service-6m',
            'event_package_slug' => 'event-one-off',
            'event_title' => 'Bundle Bakery Market',
            'advert_package_slugs' => ['sitewide-banner-30d', 'article-mid-placement-30d'],
            'push_package_slug' => 'push-campaign-city-once',
        ]);

        $order = Order::with('items')->first();

        $response->assertRedirect(route('checkout.show', $order));
        $this->assertSame('pending_payment', $order->status);
        $this->assertCount(5, $order->items);
        $this->assertDatabaseHas('listings', ['title' => 'Bundle Bakery', 'source_channel' => 'self_service']);
        $this->assertDatabaseHas('events', ['title' => 'Bundle Bakery Market', 'status' => 'draft']);
        $this->assertSame(2, AdCampaign::count());
        $this->assertSame(1, PushCampaign::count());
        $this->assertTrue($order->total > 0);

        $payment = Payment::firstOrFail();
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_transaction_id' => 'bundle-test-001',
        ]);

        $this->assertTrue(Listing::firstOrFail()->hasActiveBusinessEntitlement());
        $this->assertTrue(Event::firstOrFail()->hasActiveEventEntitlement());
        $this->assertSame(2, AdCampaign::where('status', 'active')->count());
        $this->assertTrue(PushCampaign::firstOrFail()->hasActivePushEntitlement());
    }
}
