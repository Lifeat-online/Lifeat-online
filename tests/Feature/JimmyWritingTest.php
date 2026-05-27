<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\Category;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JimmyWritingTest extends TestCase
{
    use RefreshDatabase;

    public function test_jimmy_command_creates_unpublished_article_draft_from_approved_brief(): void
    {
        $this->configureOpenRouter();

        $editor = User::factory()->create(['role' => 'editor']);
        $category = Category::create([
            'type' => 'article',
            'name' => 'Local News',
            'slug' => 'local-news',
        ]);
        $brief = $this->approvedBrief($editor, $category);

        $this->fakeJimmyResponses();

        $this->artisan('life:jimmy:write --limit=5')
            ->expectsOutputToContain('Draft created: Bethlehem water repair timeline confirmed')
            ->expectsOutputToContain('Jimmy writing complete: 1 drafted, 0 failed, 0 skipped.')
            ->assertExitCode(0);

        $article = Article::query()->where('article_brief_id', $brief->id)->firstOrFail();

        $this->assertSame('draft', $article->status);
        $this->assertSame('bethlehem-water-repair-timeline', $article->slug);
        $this->assertNull($article->published_at);
        $this->assertSame($editor->id, $article->user_id);
        $this->assertTrue($article->categories()->whereKey($category->id)->exists());

        $this->assertDatabaseHas('article_briefs', [
            'id' => $brief->id,
            'status' => ArticleBrief::STATUS_DRAFTED,
        ]);
        $this->assertDatabaseHas('content_translations', [
            'translatable_type' => Article::class,
            'translatable_id' => $article->id,
            'locale' => 'af',
            'provider' => 'openrouter',
        ]);
        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'jimmy_article_draft',
            'source_type' => ArticleBrief::class,
            'source_id' => $brief->id,
            'status' => AiGeneration::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('article_revision_notes', [
            'article_id' => $article->id,
            'author_user_id' => $editor->id,
            'status' => 'draft',
        ]);

        $tag = Tag::query()->where('slug', 'bethlehem')->firstOrFail();
        $this->assertTrue($article->tags()->whereKey($tag->id)->exists());
    }

    public function test_admin_can_ask_jimmy_to_write_one_approved_brief_from_review_queue(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::create([
            'type' => 'article',
            'name' => 'Community',
            'slug' => 'community',
        ]);
        $brief = $this->approvedBrief($admin, $category);

        $this->fakeJimmyResponses();

        $response = $this->actingAs($admin)
            ->post(route('admin.article-briefs.draft', $brief));

        $article = Article::query()->where('article_brief_id', $brief->id)->firstOrFail();

        $response
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHas('status', 'Jimmy draft article created.');

        $this->assertSame(ArticleBrief::STATUS_DRAFTED, $brief->fresh()->status);
        $this->assertSame('draft', $article->status);
    }

    public function test_approving_brief_immediately_starts_jimmy_and_creates_translation(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::create([
            'type' => 'article',
            'name' => 'Community',
            'slug' => 'community',
        ]);
        $brief = $this->approvedBrief($admin, $category, [
            'status' => ArticleBrief::STATUS_PENDING_REVIEW,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $this->fakeJimmyResponses();

        $response = $this->actingAs($admin)
            ->post(route('admin.article-briefs.approve', $brief));

        $article = Article::query()->where('article_brief_id', $brief->id)->firstOrFail();

        $response
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHas('status', 'Article brief approved. Jimmy draft article created.');

        $brief->refresh();
        $this->assertSame(ArticleBrief::STATUS_DRAFTED, $brief->status);
        $this->assertSame($admin->id, $brief->reviewed_by);
        $this->assertNotNull($brief->reviewed_at);
        $this->assertSame('draft', $article->status);

        $this->assertDatabaseHas('content_translations', [
            'translatable_type' => Article::class,
            'translatable_id' => $article->id,
            'locale' => 'af',
            'provider' => 'openrouter',
        ]);
    }

    public function test_jimmy_does_not_draft_pending_briefs(): void
    {
        $this->configureOpenRouter();

        $editor = User::factory()->create(['role' => 'editor']);
        $category = Category::create([
            'type' => 'article',
            'name' => 'Local News',
            'slug' => 'local-news',
        ]);
        $brief = $this->approvedBrief($editor, $category, [
            'status' => ArticleBrief::STATUS_PENDING_REVIEW,
        ]);

        Http::fake();

        $this->artisan('life:jimmy:write --limit=5')
            ->expectsOutputToContain('Jimmy writing complete: 0 drafted, 0 failed, 0 skipped.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('articles', [
            'article_brief_id' => $brief->id,
        ]);
        $this->assertSame(ArticleBrief::STATUS_PENDING_REVIEW, $brief->fresh()->status);
        Http::assertNothingSent();
    }

    private function approvedBrief(User $editor, Category $category, array $overrides = []): ArticleBrief
    {
        $source = ResearchSource::create([
            'name' => 'Google News: Bethlehem',
            'slug' => 'google-news-bethlehem',
            'type' => ResearchSource::TYPE_GOOGLE_NEWS_RSS,
            'query' => 'Bethlehem Free State',
            'is_active' => true,
        ]);
        $item = ResearchItem::create([
            'research_source_id' => $source->id,
            'source_name' => 'Example News',
            'source_type' => ResearchSource::TYPE_GOOGLE_NEWS_RSS,
            'source_url' => 'https://example.com/bethlehem-water-repairs',
            'title' => 'Bethlehem water repairs affect residents',
            'summary' => 'Residents in Bethlehem are affected by confirmed water repair work this week.',
            'published_at' => now(),
            'fetched_at' => now(),
            'detected_locations' => ['Bethlehem', 'Free State'],
            'fingerprint' => hash('sha256', uniqid('research-', true)),
            'status' => ResearchItem::STATUS_BRIEFED,
        ]);

        return ArticleBrief::create([
            'research_item_id' => $item->id,
            'suggested_category_id' => $category->id,
            'title' => 'Bethlehem water repair work needs local context',
            'angle' => 'Explain where residents are affected and what timeline has been confirmed.',
            'source_urls' => ['https://example.com/bethlehem-water-repairs'],
            'suggested_tags' => ['Bethlehem', 'Water', 'Dihlabeng'],
            'locality_score' => 95,
            'newsworthiness_score' => 82,
            'confidence_score' => 78,
            'duplicate_risk' => 12,
            'editorial_notes' => 'Confirm the municipal repair timeline before publishing.',
            'status' => ArticleBrief::STATUS_APPROVED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            ...$overrides,
        ]);
    }

    private function fakeJimmyResponses(): void
    {
        Http::fake([
            'https://example.com/bethlehem-water-repairs' => Http::response(
                '<html><body><h1>Bethlehem water repairs</h1><p>Dihlabeng officials said repair teams would work on the Bethlehem water line this week.</p></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Bethlehem water repair timeline confirmed',
                            'slug' => 'bethlehem-water-repair-timeline',
                            'excerpt' => 'Dihlabeng repair teams are expected to work on Bethlehem water lines this week.',
                            'body' => "Bethlehem residents should prepare for water repair work this week.\n\nAccording to the supplied source, Dihlabeng repair teams will work on a local water line. Editors should confirm the final schedule before publishing.",
                            'seo_title' => 'Bethlehem Water Repair Timeline',
                            'seo_description' => 'A local update on Bethlehem water repair work planned by Dihlabeng teams this week.',
                            'afrikaans_translation' => [
                                'title' => 'Bethlehem-waterhersteltydlyn bevestig',
                                'excerpt' => 'Dihlabeng-herstelspanne sal na verwagting hierdie week aan Bethlehem se waterlyne werk.',
                                'body' => "Bethlehem-inwoners moet hierdie week vir waterherstelwerk voorberei.\n\nVolgens die bron sal Dihlabeng-herstelspanne aan 'n plaaslike waterlyn werk.",
                            ],
                            'suggested_tags' => ['Bethlehem', 'Water', 'Dihlabeng'],
                            'source_notes' => 'Used the supplied local source page.',
                            'editorial_flags' => ['Confirm final municipal schedule before publishing.'],
                            'image_prompt' => 'Editorial illustration of water repair teams in a small Free State town.',
                        ]),
                    ],
                ]],
            ]),
        ]);
    }

    private function configureOpenRouter(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
        ]);
    }
}
