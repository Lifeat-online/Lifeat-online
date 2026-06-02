<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminActionStationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_action_station_with_ai_review_and_human_writer_payment_queues(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $writer = User::factory()->create(['role' => 'writer']);

        $listing = Listing::factory()->create([
            'title' => 'Draft Bakery',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $article = Article::create([
            'user_id' => $writer->id,
            'title' => 'Paid local story',
            'slug' => 'paid-local-story',
            'excerpt' => 'A local story by a staff writer.',
            'body' => 'This approved article has payable words.',
            'status' => 'published',
            'submitted_at' => now(),
            'published_at' => now(),
        ]);

        ArticleWordLedger::create([
            'article_id' => $article->id,
            'writer_user_id' => $writer->id,
            'approved_by_user_id' => $admin->id,
            'word_count' => 500,
            'rate_per_word' => 1.50,
            'gross_amount' => 750,
            'status' => 'pending',
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.action-station.index'))
            ->assertOk()
            ->assertSee('Action Station')
            ->assertSee('AI Content Review')
            ->assertSee('Draft Bakery')
            ->assertSee('Writer Payments')
            ->assertSee('Staff writers are paid for writing')
            ->assertSee('Paid local story')
            ->assertSee('Human payout approval');
    }

    public function test_ai_approved_listing_can_be_auto_published_from_action_station(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $listing = Listing::factory()->create([
            'title' => 'Bethlehem Auto Glass',
            'description' => 'Windscreen repairs and replacements for Bethlehem drivers.',
            'status' => 'draft',
            'published_at' => null,
        ]);

        Setting::create([
            'key' => 'action_station.auto_publish_content',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'action_station',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'recommendation' => 'approve',
                            'quality_score' => 91,
                            'safety_score' => 96,
                            'confidence_score' => 88,
                            'reasons' => ['Specific local service with safe claims.'],
                            'blocking_flags' => [],
                            'suggested_fixes' => [],
                            'public_summary' => 'Local auto glass listing is safe to publish.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.action-station.review'), [
                'type' => 'listing',
                'id' => $listing->id,
            ])
            ->assertRedirect(route('admin.action-station.index'));

        $this->assertSame('published', $listing->fresh()->status);
        $this->assertNotNull($listing->fresh()->published_at);
        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'content_review',
            'source_type' => Listing::class,
            'source_id' => $listing->id,
            'status' => AiGeneration::STATUS_ACCEPTED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.action-station.index'))
            ->assertOk()
            ->assertSee('Approved Content Report')
            ->assertSee('Bethlehem Auto Glass')
            ->assertSee('Published');
    }

    public function test_ai_denied_content_is_routed_to_action_station_without_publishing(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $listing = Listing::factory()->create([
            'title' => 'Too Good Claims',
            'description' => 'Guaranteed financial returns and miracle legal solutions overnight.',
            'status' => 'draft',
            'published_at' => null,
        ]);

        Setting::create([
            'key' => 'action_station.auto_publish_content',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'action_station',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'recommendation' => 'reject',
                            'quality_score' => 42,
                            'safety_score' => 20,
                            'confidence_score' => 92,
                            'reasons' => ['Unsupported financial and legal promises.'],
                            'blocking_flags' => ['Unsupported financial promises', 'Unsupported legal claims'],
                            'suggested_fixes' => ['Remove guarantees and provide verifiable service details.'],
                            'public_summary' => 'Content needs human intervention before publication.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.action-station.review'), [
                'type' => 'listing',
                'id' => $listing->id,
            ])
            ->assertRedirect(route('admin.action-station.index'));

        $this->assertSame('draft', $listing->fresh()->status);
        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'content_review',
            'source_type' => Listing::class,
            'source_id' => $listing->id,
            'status' => AiGeneration::STATUS_REJECTED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.action-station.index'))
            ->assertOk()
            ->assertSee('Denied Or Blocked Content')
            ->assertSee('Too Good Claims')
            ->assertSee('Unsupported financial promises');
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
