<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Event;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_admin_api_endpoints_respond_ok(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $listing = Listing::factory()->create([
            'status' => 'draft',
        ]);

        $event = Event::create([
            'user_id' => $admin->id,
            'listing_id' => null,
            'title' => 'Test Event',
            'slug' => 'test-event-'.Str::lower(Str::random(6)),
            'excerpt' => null,
            'description' => null,
            'venue_name' => null,
            'address_line' => null,
            'city' => null,
            'region' => null,
            'country' => null,
            'postal_code' => null,
            'start_at' => now()->addDays(2),
            'end_at' => null,
            'website_url' => null,
            'featured_image' => null,
            'status' => 'draft',
            'published_at' => null,
            'is_all_day' => false,
        ]);

        $article = Article::create([
            'user_id' => $admin->id,
            'title' => 'Test Article',
            'slug' => 'test-article-'.Str::lower(Str::random(6)),
            'excerpt' => null,
            'body' => null,
            'featured_image' => null,
            'status' => 'draft',
            'submitted_at' => null,
            'published_at' => null,
            'editor_user_id' => null,
        ]);

        $this->actingAs($admin)->getJson(route('api.admin.listings.index'))->assertOk();
        $this->actingAs($admin)->getJson(route('api.admin.listings.show', $listing))->assertOk();
        $this->actingAs($admin)->putJson(route('api.admin.listings.update', $listing), [
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
            'status' => 'draft',
            'published_at' => null,
            'category_ids' => [],
        ])->assertOk();

        $this->actingAs($admin)->getJson(route('api.admin.events.index'))->assertOk();
        $this->actingAs($admin)->getJson(route('api.admin.events.show', $event))->assertOk();

        $this->actingAs($admin)->getJson(route('api.admin.articles.index'))->assertOk();
        $this->actingAs($admin)->getJson(route('api.admin.articles.show', $article))->assertOk();
    }
}
