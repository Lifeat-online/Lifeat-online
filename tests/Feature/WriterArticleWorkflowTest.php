<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\WriterApplication;
use App\Services\OpenRouterTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class WriterArticleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_dashboard_shows_onboarding_next_steps_for_new_writer(): void
    {
        $writer = User::factory()->create([
            'role' => 'writer',
            'email' => 'new-writer@example.com',
        ]);

        WriterApplication::create([
            'user_id' => $writer->id,
            'first_name' => 'New',
            'last_name' => 'Writer',
            'email' => 'new-writer@example.com',
            'phone' => '082 000 1000',
            'username' => 'new_writer',
            'profile_bio' => str_repeat('Approved writer onboarding test profile. ', 4),
            'profile_photo_path' => 'writer-applications/profile-photos/new.jpg',
            'available_on_whatsapp' => true,
            'sample_article_title' => 'Sample article',
            'sample_article_body' => str_repeat('Sample article body for onboarding. ', 8),
            'sample_advert_title' => 'Sample advert',
            'sample_advert_body' => str_repeat('Sample advert body for onboarding. ', 4),
            'id_document_path' => 'writer-applications/id-documents/id.pdf',
            'proof_of_residence_path' => 'writer-applications/proof-of-residence/home.pdf',
            'status' => WriterApplication::STATUS_APPROVED,
            'assigned_role' => WriterApplication::ROLE_WRITER,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHour(),
            'onboarded_at' => now()->subHour(),
            'access_notified_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($writer)->get(route('writer.articles.index'));

        $response->assertOk();
        $response->assertSee('Writer Onboarding Checklist');
        $response->assertSee('1 of 5 steps complete.');
        $response->assertSee('Next: First article draft');
        $response->assertSee('Start with one focused local story');
        $response->assertSee('No payout details are needed until approved work creates a payable ledger entry.');
    }

    public function test_writer_dashboard_and_earnings_show_review_and_payment_expectations(): void
    {
        $writer = User::factory()->create([
            'role' => 'writer',
        ]);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Published Story',
            'slug' => 'published-story',
            'excerpt' => 'Published story excerpt.',
            'body' => 'This published article has enough words to create a writer ledger entry.',
            'status' => 'published',
            'submitted_at' => now()->subDay(),
            'published_at' => now(),
        ]);

        ArticleWordLedger::create([
            'article_id' => $article->id,
            'writer_user_id' => $writer->id,
            'word_count' => $article->wordCount(),
            'rate_per_word' => 1.50,
            'gross_amount' => 12.00,
            'status' => 'pending',
            'approved_at' => now(),
        ]);

        $indexResponse = $this->actingAs($writer)->get(route('writer.articles.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('5 of 5 steps complete.');
        $indexResponse->assertSee('Ledger totals: pending R12.00');

        $earningsResponse = $this->actingAs($writer)->get(route('writer.earnings.index'));
        $earningsResponse->assertOk();
        $earningsResponse->assertSee('Payout expectations');
        $earningsResponse->assertSee('Pending ledger entries are reviewed by finance');
        $earningsResponse->assertSee('Published Story');
    }

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

        $this->actingAs($writer)
            ->get(route('writer.articles.create'))
            ->assertOk()
            ->assertSee('Payment is only calculated after an editor publishes the article')
            ->assertSee('Submit for review when the title, excerpt, body, category, and local angle are ready');

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
