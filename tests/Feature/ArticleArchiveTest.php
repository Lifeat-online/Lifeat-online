<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_category_archive_only_shows_matching_published_articles(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);

        $localNews = Category::create([
            'type' => 'article',
            'name' => 'Local News',
            'slug' => 'local-news',
            'description' => 'Town and regional reporting.',
        ]);

        $sport = Category::create([
            'type' => 'article',
            'name' => 'Sport',
            'slug' => 'sport',
        ]);

        $matchingArticle = Article::create([
            'user_id' => $writer->id,
            'title' => 'Community Hall Reopens',
            'slug' => 'community-hall-reopens',
            'excerpt' => 'The local hall has reopened.',
            'body' => 'Residents gathered for the reopening of the community hall.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $matchingArticle->categories()->attach($localNews);

        $otherArticle = Article::create([
            'user_id' => $writer->id,
            'title' => 'Weekend Sports Roundup',
            'slug' => 'weekend-sports-roundup',
            'excerpt' => 'Latest local sports results.',
            'body' => 'A quick roundup of this weekend in sport.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $otherArticle->categories()->attach($sport);

        $response = $this->get(route('articles.categories.show', $localNews));

        $response->assertOk();
        $response->assertSee('Local News Articles');
        $response->assertSee('Town and regional reporting.');
        $response->assertSee('Community Hall Reopens');
        $response->assertDontSee('Weekend Sports Roundup');
    }

    public function test_article_detail_links_category_badges_to_archive_pages(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);

        $category = Category::create([
            'type' => 'article',
            'name' => 'Community',
            'slug' => 'community',
        ]);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Library Fundraiser',
            'slug' => 'library-fundraiser',
            'excerpt' => 'Support the library fundraiser.',
            'body' => 'The community library is hosting a fundraiser this month.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $article->categories()->attach($category);

        $response = $this->get(route('articles.show', $article));

        $response->assertOk();
        $response->assertSee(route('articles.categories.show', $category), false);
        $response->assertSee('Back to all articles');
    }
}
