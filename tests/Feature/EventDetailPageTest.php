<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_detail_page_renders_core_profile_sections(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);

        $listingPackage = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $eventPackage = Package::where('slug', 'event-one-off')->firstOrFail();

        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Blue Crane Bakery',
            'slug' => 'blue-crane-bakery',
            'status' => 'published',
            'excerpt' => 'Fresh local bakery and cafe.',
            'description' => 'Blue Crane Bakery serves breads, pastries, and coffee for the local community.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'phone' => '058 000 0000',
            'email' => 'hello@example.com',
            'address_line' => '12 Muller Street',
            'website_url' => 'https://example.com',
        ]);

        $listingSubscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $listingPackage->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        $listing->update([
            'active_subscription_id' => $listingSubscription->id,
        ]);

        $event = Event::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Blue Crane Winter Market',
            'slug' => 'blue-crane-winter-market',
            'status' => 'published',
            'excerpt' => 'An evening market with fresh bakes and local makers.',
            'description' => 'Join us for a local market with baked goods, coffee, and family-friendly stalls.',
            'venue_name' => 'Blue Crane Courtyard',
            'address_line' => '12 Muller Street',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(4),
            'website_url' => 'https://example.com/events/winter-market',
        ]);

        $eventSubscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $eventPackage->id,
            'subscribable_type' => Event::class,
            'subscribable_id' => $event->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        $event->update([
            'active_subscription_id' => $eventSubscription->id,
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('Blue Crane Winter Market');
        $response->assertSee('About This Event');
        $response->assertSee('Venue and Schedule');
        $response->assertSee('Event Details');
        $response->assertSee('Related Events');
        $response->assertSee('Promote your event');
    }
}
