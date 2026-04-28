<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\LocationNode;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTagAndLocationSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_archive_only_shows_matching_published_articles(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);

        $newsTag = Tag::create([
            'type' => 'article',
            'name' => 'Breaking',
            'slug' => 'breaking',
            'description' => 'Fast-moving local updates.',
        ]);

        $featureTag = Tag::create([
            'type' => 'article',
            'name' => 'Feature',
            'slug' => 'feature',
        ]);

        $matchingArticle = Article::create([
            'user_id' => $writer->id,
            'title' => 'Bridge Closure Update',
            'slug' => 'bridge-closure-update',
            'excerpt' => 'Important traffic update.',
            'body' => 'The bridge closure continues to affect morning traffic.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $matchingArticle->tags()->attach($newsTag);

        $otherArticle = Article::create([
            'user_id' => $writer->id,
            'title' => 'Weekend Long Read',
            'slug' => 'weekend-long-read',
            'excerpt' => 'Feature article.',
            'body' => 'A long feature article for the weekend edition.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $otherArticle->tags()->attach($featureTag);

        $response = $this->get(route('articles.tags.show', $newsTag));

        $response->assertOk();
        $response->assertSee('Breaking Articles');
        $response->assertSee('Fast-moving local updates.');
        $response->assertSee('Bridge Closure Update');
        $response->assertDontSee('Weekend Long Read');
    }

    public function test_location_archive_only_shows_matching_published_articles(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);

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

        $matchingArticle = Article::create([
            'user_id' => $writer->id,
            'title' => 'Bethlehem Water Notice',
            'slug' => 'bethlehem-water-notice',
            'excerpt' => 'Water maintenance notice.',
            'body' => 'Scheduled maintenance affects several Bethlehem streets.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $matchingArticle->locations()->attach($bethlehem);

        $otherArticle = Article::create([
            'user_id' => $writer->id,
            'title' => 'Clarens Market Dates',
            'slug' => 'clarens-market-dates',
            'excerpt' => 'Market schedule update.',
            'body' => 'Clarens market dates have been updated for the month.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $otherArticle->locations()->attach($clarens);

        $response = $this->get(route('articles.locations.show', $bethlehem));

        $response->assertOk();
        $response->assertSee('Bethlehem Articles');
        $response->assertSee('Browse published articles linked to Bethlehem.');
        $response->assertSee('Bethlehem Water Notice');
        $response->assertDontSee('Clarens Market Dates');
    }

    public function test_writer_can_save_tags_and_locations_on_article_submission(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);

        $category = Category::create([
            'type' => 'article',
            'name' => 'Local News',
            'slug' => 'local-news',
        ]);

        $tag = Tag::create([
            'type' => 'article',
            'name' => 'Community',
            'slug' => 'community',
        ]);

        $location = LocationNode::create([
            'name' => 'Harrismith',
            'slug' => 'harrismith',
            'type' => 'town',
        ]);

        $response = $this->actingAs($writer)->post(route('writer.articles.store'), [
            'title' => 'Clinic Upgrade Approved',
            'slug' => 'clinic-upgrade-approved',
            'excerpt' => 'Clinic improvements approved.',
            'body' => 'Local officials approved the next round of clinic upgrades.',
            'category_ids' => [$category->id],
            'tag_ids' => [$tag->id],
            'location_ids' => [$location->id],
            'submit_for_review' => '1',
        ]);

        $response->assertRedirect();

        $article = Article::where('slug', 'clinic-upgrade-approved')->firstOrFail();

        $this->assertSame([$tag->id], $article->tags()->pluck('tags.id')->all());
        $this->assertSame([$location->id], $article->locations()->pluck('location_nodes.id')->all());
    }
}
