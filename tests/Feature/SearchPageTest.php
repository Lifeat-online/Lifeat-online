<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\LocationNode;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_filters_grouped_results_by_keyword_and_location(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $bethlehem = LocationNode::create([
            'name' => 'Bethlehem',
            'slug' => 'bethlehem',
            'type' => 'city',
        ]);
        $clarens = LocationNode::create([
            'name' => 'Clarens',
            'slug' => 'clarens',
            'type' => 'town',
        ]);

        $matchingListing = $this->createPublishedListing($owner, [
            'title' => 'Bethlehem Market Hub',
            'slug' => 'bethlehem-market-hub',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'description' => 'A market-focused business in Bethlehem.',
        ]);

        $this->createPublishedListing($owner, [
            'title' => 'Clarens Market Hub',
            'slug' => 'clarens-market-hub',
            'city' => 'Clarens',
            'region' => 'Free State',
            'description' => 'This result should be filtered out by location.',
        ]);

        $matchingEvent = $this->createPublishedEvent($owner, $matchingListing, [
            'title' => 'Bethlehem Market Night',
            'slug' => 'bethlehem-market-night',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'description' => 'A local market event.',
        ]);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Bethlehem Market Guide',
            'slug' => 'bethlehem-market-guide',
            'excerpt' => 'A guide to the best market spots in town.',
            'body' => 'This Bethlehem market guide helps locals discover stalls and events.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $article->locations()->attach($bethlehem);

        $otherArticle = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Clarens Market Guide',
            'slug' => 'clarens-market-guide',
            'excerpt' => 'A guide to market stops outside Bethlehem.',
            'body' => 'Market guide for Clarens visitors.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $otherArticle->locations()->attach($clarens);

        $classified = Classified::create([
            'title' => 'Market Stall Fridge',
            'slug' => 'market-stall-fridge',
            'description' => 'Useful fridge for a Bethlehem market stall.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get(route('search.index', [
            'q' => 'market',
            'loc' => 'Bethlehem',
        ]));

        $response->assertOk();
        $response->assertSee('Businesses');
        $response->assertSee('Events');
        $response->assertSee('Articles');
        $response->assertSee('Classifieds');
        $response->assertSee($matchingListing->title);
        $response->assertSee($matchingEvent->title);
        $response->assertSee($article->title);
        $response->assertSee($classified->title);
        $response->assertDontSee('Clarens Market Hub');
        $response->assertDontSee($otherArticle->title);
    }

    public function test_search_page_applies_category_filter_to_supported_content_types(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);

        $category = Category::create([
            'type' => 'listing',
            'name' => 'Food',
            'slug' => 'food',
        ]);

        $matchingListing = $this->createPublishedListing($owner, [
            'title' => 'Food Listing',
            'slug' => 'food-listing',
        ]);
        $matchingListing->categories()->attach($category);

        $otherListing = $this->createPublishedListing($owner, [
            'title' => 'Services Listing',
            'slug' => 'services-listing',
        ]);

        $matchingEvent = $this->createPublishedEvent($owner, $matchingListing, [
            'title' => 'Food Festival',
            'slug' => 'food-festival',
        ]);
        $matchingEvent->categories()->attach($category);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Food Feature',
            'slug' => 'food-feature',
            'excerpt' => 'Food article excerpt.',
            'body' => 'Food article body for search filtering.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $article->categories()->attach($category);

        Classified::create([
            'title' => 'Food Classified',
            'slug' => 'food-classified',
            'description' => 'This should not appear during a category-filtered search yet.',
            'city' => 'Bethlehem',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get(route('search.index', [
            'category' => $category->slug,
        ]));

        $response->assertOk();
        $response->assertSee($matchingListing->title);
        $response->assertSee($matchingEvent->title);
        $response->assertSee($article->title);
        $response->assertDontSee($otherListing->title);
        $response->assertDontSee('Food Classified');
    }

    public function test_search_page_ranks_title_matches_and_includes_taxonomy_matches(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);

        $category = Category::create([
            'type' => 'listing',
            'name' => 'Pizza',
            'slug' => 'pizza',
        ]);

        $titleMatch = $this->createPublishedListing($owner, [
            'title' => 'Pizza Palace',
            'slug' => 'pizza-palace',
            'description' => 'Family meals near the town square.',
            'is_featured' => false,
        ]);

        $descriptionOnlyMatch = $this->createPublishedListing($owner, [
            'title' => 'Market Supper Club',
            'slug' => 'market-supper-club',
            'description' => 'Wood-fired pizza and dinner specials.',
            'is_featured' => true,
        ]);

        $taxonomyOnlyMatch = $this->createPublishedListing($owner, [
            'title' => 'Bethlehem Hearth',
            'slug' => 'bethlehem-hearth',
            'description' => 'Casual family restaurant.',
            'is_featured' => false,
        ]);
        $taxonomyOnlyMatch->categories()->attach($category);

        $response = $this->get(route('search.index', ['q' => 'pizza']));

        $response->assertOk();
        $response->assertSee($taxonomyOnlyMatch->title);
        $response->assertSeeInOrder([
            $titleMatch->title,
            $descriptionOnlyMatch->title,
        ]);
    }

    private function createPublishedListing(User $owner, array $attributes = []): Listing
    {
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();

        $listing = Listing::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Sample Listing',
            'slug' => 'sample-listing-'.fake()->unique()->slug(),
            'excerpt' => 'Directory listing excerpt.',
            'description' => 'Directory listing description.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'country' => 'South Africa',
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        return $listing->fresh();
    }

    private function createPublishedEvent(User $owner, Listing $listing, array $attributes = []): Event
    {
        $package = Package::where('slug', 'event-one-off')->firstOrFail();

        $event = Event::create(array_merge([
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'title' => 'Sample Event',
            'slug' => 'sample-event-'.fake()->unique()->slug(),
            'excerpt' => 'Event excerpt.',
            'description' => 'Event description.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'country' => 'South Africa',
            'status' => 'published',
            'start_at' => now()->addWeek(),
            'published_at' => now(),
        ], $attributes));

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Event::class,
            'subscribable_id' => $event->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        $event->update([
            'active_subscription_id' => $subscription->id,
            'package_expires_at' => $subscription->ends_at,
        ]);

        return $event->fresh();
    }
}
