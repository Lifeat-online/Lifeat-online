<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Services\OpenRouterTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class WriterArticleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_submit_article_for_review(): void
    {
        $writer = User::factory()->create([
            'role' => 'writer',
        ]);

        $category = Category::create([
            'type' => 'article',
            'name' => 'Local News',
            'slug' => 'local-news',
        ]);

        $response = $this->actingAs($writer)->post(route('writer.articles.store'), [
            'title' => 'My First Story',
            'slug' => 'my-first-story',
            'excerpt' => 'Short excerpt',
            'body' => 'This is a local article body with enough words for testing.',
            'category_ids' => [$category->id],
            'submit_for_review' => '1',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'title' => 'My First Story',
            'user_id' => $writer->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_publishing_article_creates_word_ledger_entry(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Setting::query()->where('key', 'writer.per_word_rate')->update(['value' => '1.50']);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Publish Me',
            'slug' => 'publish-me',
            'excerpt' => 'Short excerpt',
            'body' => 'This article body contains enough words to calculate a ledger entry correctly.',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'status' => 'published',
            'category_ids' => [],
        ]);

        $response->assertRedirect(route('admin.articles.edit', $article));

        $article->refresh();
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->wordLedger);
        $this->assertSame($article->wordCount(), $article->wordLedger->word_count);
        $this->assertEquals('1.50', $article->wordLedger->rate_per_word);
    }

    public function test_publishing_english_article_requests_afrikaans_translation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Community Update',
            'slug' => 'community-update',
            'excerpt' => 'Short excerpt',
            'body' => 'This article body is ready for a bilingual publishing workflow.',
            'source_locale' => 'en',
            'status' => 'pending_review',
        ]);

        $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock) use ($article): void {
            $mock->shouldReceive('translateModel')
                ->once()
                ->withArgs(fn (Article $target, string $locale): bool => $target->is($article) && $locale === 'af')
                ->andReturn(['ok' => true, 'message' => 'Translation saved.']);
        });

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'source_locale' => 'en',
            'status' => 'published',
            'category_ids' => [],
        ])->assertRedirect(route('admin.articles.edit', $article));
    }

    public function test_publishing_afrikaans_article_requests_english_translation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Gemeenskapsnuus',
            'slug' => 'gemeenskapsnuus',
            'excerpt' => 'Kort uittreksel',
            'body' => 'Hierdie Afrikaanse artikel moet ook in Engels beskikbaar wees.',
            'source_locale' => 'af',
            'status' => 'pending_review',
        ]);

        $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock) use ($article): void {
            $mock->shouldReceive('translateModel')
                ->once()
                ->withArgs(fn (Article $target, string $locale): bool => $target->is($article) && $locale === 'en')
                ->andReturn(['ok' => true, 'message' => 'Translation saved.']);
        });

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'source_locale' => 'af',
            'status' => 'published',
            'category_ids' => [],
        ])->assertRedirect(route('admin.articles.edit', $article));
    }

    public function test_revision_request_note_is_saved_for_article(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Needs Work',
            'slug' => 'needs-work',
            'excerpt' => 'Short excerpt',
            'body' => 'This draft needs a better ending and more local detail.',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'status' => 'revision_requested',
            'revision_note' => 'Please expand the local angle and add interview quotes.',
            'category_ids' => [],
        ]);

        $response->assertRedirect(route('admin.articles.edit', $article));

        $this->assertDatabaseHas('article_revision_notes', [
            'article_id' => $article->id,
            'author_user_id' => $admin->id,
            'status' => 'revision_requested',
        ]);
    }
}
