<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleAuthorArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_archive_only_shows_matching_published_articles(): void
    {
        $writer = User::factory()->create([
            'role' => 'writer',
            'name' => 'Nina Reporter',
            'username' => 'nina-reporter',
            'bio' => 'Local reporting from across the Eastern Freestate.',
        ]);

        $otherWriter = User::factory()->create([
            'role' => 'writer',
            'name' => 'Sam Writer',
            'username' => 'sam-writer',
        ]);

        Article::create([
            'user_id' => $writer->id,
            'title' => 'Town Budget Meeting',
            'slug' => 'town-budget-meeting',
            'excerpt' => 'Budget meeting summary.',
            'body' => 'Published coverage of the town budget meeting.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Article::create([
            'user_id' => $otherWriter->id,
            'title' => 'Sports Awards Night',
            'slug' => 'sports-awards-night',
            'excerpt' => 'Awards night recap.',
            'body' => 'Coverage of the annual sports awards night.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get(route('articles.authors.show', $writer));

        $response->assertOk();
        $response->assertSee('Articles by Nina Reporter');
        $response->assertSee('Local reporting from across the Eastern Freestate.');
        $response->assertSee('Town Budget Meeting');
        $response->assertDontSee('Sports Awards Night');
    }

    public function test_article_archive_links_author_names_to_author_pages_when_username_exists(): void
    {
        $writer = User::factory()->create([
            'role' => 'writer',
            'name' => 'Lebo Nkosi',
            'username' => 'lebo-nkosi',
        ]);

        Article::create([
            'user_id' => $writer->id,
            'title' => 'Library Expansion Approved',
            'slug' => 'library-expansion-approved',
            'excerpt' => 'Expansion approved.',
            'body' => 'The town library expansion was approved this week.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->get(route('articles.index'));

        $response->assertOk();
        $response->assertSee(route('articles.authors.show', $writer), false);
        $response->assertSee('Lebo Nkosi');
    }
}
