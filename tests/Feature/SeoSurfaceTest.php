<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_outputs_core_seo_tags_and_default_schema(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('<link rel="canonical" href="'.route('home').'">', false);
        $response->assertSee('<meta property="og:title" content="Eastern Freestate | Home">', false);
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('SearchAction', false);
    }

    public function test_public_detail_pages_emit_specific_schema_and_canonical_links(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = $this->activeListing($owner);
        $event = $this->activeEvent($owner, $listing);
        $article = Article::create([
            'user_id' => $owner->id,
            'title' => 'Local SEO Story',
            'slug' => 'local-seo-story',
            'excerpt' => 'A focused local story for SEO verification.',
            'seo_title' => 'Local SEO Story',
            'seo_description' => 'A search-ready description for the local SEO story.',
            'body' => 'Local story body.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get(route('directory.show', $listing))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('directory.show', $listing).'">', false)
            ->assertSee('"@type":"LocalBusiness"', false);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('events.show', $event).'">', false)
            ->assertSee('"@type":"Event"', false);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('articles.show', $article).'">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"NewsArticle"', false);
    }

    public function test_sitemap_lists_static_and_public_dynamic_routes(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = $this->activeListing($owner);
        $article = Article::create([
            'user_id' => $owner->id,
            'title' => 'Sitemap Story',
            'slug' => 'sitemap-story',
            'excerpt' => 'A sitemap visible article.',
            'body' => 'Sitemap story body.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
        $response->assertSee('<loc>'.route('home').'</loc>', false);
        $response->assertSee('<loc>'.route('directory.show', $listing).'</loc>', false);
        $response->assertSee('<loc>'.route('articles.show', $article).'</loc>', false);
    }

    public function test_robots_file_points_to_sitemap(): void
    {
        $this->assertStringContainsString('Sitemap: /sitemap.xml', file_get_contents(public_path('robots.txt')));
    }

    private function activeListing(User $owner): Listing
    {
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'SEO Test Business',
            'slug' => 'seo-test-business',
            'excerpt' => 'A public business profile for SEO verification.',
            'description' => 'A public business profile for SEO verification.',
            'phone' => '0580000000',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'country' => 'South Africa',
            'status' => 'published',
            'published_at' => now(),
            'package_expires_at' => now()->addMonths(6),
        ]);
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(6),
            'renews_at' => now()->addMonths(6),
            'renewal_mode' => 'manual',
        ]);
        $listing->update(['active_subscription_id' => $subscription->id]);

        return $listing->fresh();
    }

    private function activeEvent(User $owner, Listing $listing): Event
    {
        $package = Package::where('slug', 'event-one-off')->firstOrFail();
        $event = Event::create([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'SEO Test Event',
            'slug' => 'seo-test-event',
            'excerpt' => 'A public event for SEO verification.',
            'description' => 'A public event for SEO verification.',
            'venue_name' => 'Town Hall',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'country' => 'South Africa',
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(2),
            'status' => 'published',
            'published_at' => now(),
            'package_expires_at' => now()->addMonth(),
        ]);
        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Event::class,
            'subscribable_id' => $event->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);
        $event->update(['active_subscription_id' => $subscription->id]);

        return $event->fresh();
    }
}
