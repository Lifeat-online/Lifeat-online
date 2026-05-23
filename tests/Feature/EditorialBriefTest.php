<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\ArticleBrief;
use App\Models\Category;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EditorialBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_editorial_brief_command_creates_reviewable_brief_from_research_item(): void
    {
        $this->configureOpenRouter();

        $category = Category::create([
            'type' => 'article',
            'name' => 'Local News',
            'slug' => 'local-news',
        ]);
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
            'summary' => 'Residents in Bethlehem are affected by water repair work this week.',
            'published_at' => now(),
            'fetched_at' => now(),
            'detected_locations' => ['Bethlehem', 'Free State'],
            'fingerprint' => hash('sha256', 'https://example.com/bethlehem-water-repairs'),
            'status' => ResearchItem::STATUS_NEW,
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Bethlehem water repair work needs local context',
                            'angle' => 'Explain where residents are affected and what timelines have been confirmed.',
                            'source_urls' => ['https://example.com/bethlehem-water-repairs'],
                            'category' => 'local-news',
                            'suggested_tags' => ['Bethlehem', 'Water', 'Dihlabeng'],
                            'locality_score' => 95,
                            'newsworthiness_score' => 82,
                            'confidence_score' => 78,
                            'duplicate_risk' => 12,
                            'editorial_notes' => 'Confirm the municipal repair timeline before Jimmy writes.',
                            'recommendation' => 'review',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->artisan('life:editorial:brief --limit=5')
            ->expectsOutputToContain('Editorial briefs complete: 1 created, 0 failed, 0 skipped.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('article_briefs', [
            'research_item_id' => $item->id,
            'suggested_category_id' => $category->id,
            'title' => 'Bethlehem water repair work needs local context',
            'status' => ArticleBrief::STATUS_PENDING_REVIEW,
        ]);
        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'editorial_brief',
            'provider' => 'openrouter',
            'status' => AiGeneration::STATUS_DRAFT,
        ]);
        $this->assertSame(ResearchItem::STATUS_BRIEFED, $item->fresh()->status);

        $brief = ArticleBrief::firstOrFail();
        $this->assertSame(['Bethlehem', 'Water', 'Dihlabeng'], $brief->suggested_tags);
        $this->assertSame(['https://example.com/bethlehem-water-repairs'], $brief->source_urls);
    }

    public function test_admin_can_review_edit_approve_and_reject_article_briefs(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::create([
            'type' => 'article',
            'name' => 'Community',
            'slug' => 'community',
        ]);
        $brief = $this->brief();
        $rejectedBrief = $this->brief([
            'title' => 'Weak press release item',
            'status' => ArticleBrief::STATUS_PENDING_REVIEW,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.article-briefs.index'))
            ->assertOk()
            ->assertSee('Brief Review Queue')
            ->assertSee('Approve for Jimmy')
            ->assertSee($brief->title);

        $this->actingAs($admin)
            ->put(route('admin.article-briefs.update', $brief), [
                'title' => 'Updated brief headline',
                'angle' => 'Updated local angle for the editor.',
                'source_urls' => "https://example.com/source-one\nhttps://example.com/source-two",
                'suggested_category_id' => $category->id,
                'suggested_tags' => 'Bethlehem, Community, Schools',
                'locality_score' => 88,
                'newsworthiness_score' => 72,
                'confidence_score' => 80,
                'duplicate_risk' => 5,
                'editorial_notes' => 'Ready for approval.',
            ])
            ->assertRedirect(route('admin.article-briefs.index'));

        $brief->refresh();
        $this->assertSame('Updated brief headline', $brief->title);
        $this->assertSame($category->id, $brief->suggested_category_id);
        $this->assertSame(['Bethlehem', 'Community', 'Schools'], $brief->suggested_tags);
        $this->assertSame(['https://example.com/source-one', 'https://example.com/source-two'], $brief->source_urls);

        $this->actingAs($admin)
            ->post(route('admin.article-briefs.approve', $brief))
            ->assertRedirect(route('admin.article-briefs.index'));

        $brief->refresh();
        $this->assertSame(ArticleBrief::STATUS_APPROVED, $brief->status);
        $this->assertSame($admin->id, $brief->reviewed_by);
        $this->assertNotNull($brief->reviewed_at);

        $this->actingAs($admin)
            ->post(route('admin.article-briefs.reject', $rejectedBrief), [
                'rejection_reason' => 'Not local enough.',
            ])
            ->assertRedirect(route('admin.article-briefs.index'));

        $rejectedBrief->refresh();
        $this->assertSame(ArticleBrief::STATUS_REJECTED, $rejectedBrief->status);
        $this->assertSame('Not local enough.', $rejectedBrief->rejection_reason);
    }

    private function brief(array $overrides = []): ArticleBrief
    {
        $item = ResearchItem::create([
            'source_name' => 'Example News',
            'source_type' => ResearchSource::TYPE_RSS,
            'source_url' => 'https://example.com/'.uniqid('story-', true),
            'title' => 'Community story signal',
            'summary' => 'A local research item.',
            'fetched_at' => now(),
            'fingerprint' => hash('sha256', uniqid('research-', true)),
            'status' => ResearchItem::STATUS_BRIEFED,
        ]);

        return ArticleBrief::create([
            'research_item_id' => $item->id,
            'title' => 'Community story brief',
            'angle' => 'A local story angle.',
            'source_urls' => [$item->source_url],
            'suggested_tags' => ['Community'],
            'locality_score' => 80,
            'newsworthiness_score' => 70,
            'confidence_score' => 75,
            'duplicate_risk' => 10,
            'editorial_notes' => 'Review before Jimmy writes.',
            'status' => ArticleBrief::STATUS_PENDING_REVIEW,
            ...$overrides,
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
