<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\Classified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AskLifeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_life_guides_business_directory_onboarding_without_ai_provider(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $this->postJson(route('ask-life.store'), [
            'question' => 'Can you assist me adding my business to your directory?',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'guided')
            ->assertJsonPath('sources.0.id', 'guide:add-listing')
            ->assertJsonPath('sources.0.url', route('add-listing.index'))
            ->assertJsonPath('sources.1.id', 'guide:advertise')
            ->assertJsonPath('search_url', null)
            ->assertSee('Start on Add Listing', false);

        Http::assertNothingSent();
    }

    public function test_ask_life_returns_local_sources_without_ai_provider(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        Article::create([
            'title' => 'Bethlehem Water Repairs',
            'slug' => 'bethlehem-water-repairs',
            'excerpt' => 'Municipal teams are repairing water lines in Bethlehem this week.',
            'body' => 'Residents in Bethlehem should prepare for water repair work.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->postJson(route('ask-life.store'), [
            'question' => 'What is happening with water in Bethlehem?',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'fallback')
            ->assertJsonPath('sources.0.type', 'article')
            ->assertJsonPath('sources.0.title', 'Bethlehem Water Repairs');

        Http::assertNothingSent();
    }

    public function test_ask_life_uses_ai_when_configured_and_logs_generation(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
        ]);

        $classified = Classified::create([
            'title' => 'Mahindra Bakkie For Sale',
            'slug' => 'mahindra-bakkie-for-sale',
            'description' => 'A 2012 Mahindra bakkie in Bethlehem, needs minor work.',
            'price' => 55000,
            'currency' => 'ZAR',
            'city' => 'Bethlehem',
            'status' => Classified::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'answer' => 'There is a Mahindra bakkie classified in Bethlehem listed for ZAR 55,000.00.',
                                'confidence' => 0.82,
                                'source_ids' => ['classified:'.$classified->id],
                                'follow_up_questions' => ['Do you want classifieds only?'],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson(route('ask-life.store'), [
            'question' => 'Mahindra bakkie in Bethlehem',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('answer', 'There is a Mahindra bakkie classified in Bethlehem listed for ZAR 55,000.00.')
            ->assertJsonPath('sources.0.id', 'classified:'.$classified->id);

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life',
            'provider' => 'openrouter',
            'model' => 'openai/gpt-oss-120b',
            'status' => AiGeneration::STATUS_DRAFT,
        ]);
    }

    public function test_jimmy_can_answer_conversationally_from_platform_guides(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
            'services.ai.providers.openrouter.model' => 'openai/gpt-oss-120b',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'answer' => 'I am Jimmy. I can help you find your way around Life@, but I will not pretend we have a verified listing or event when we do not. Tell me the town and what you need, and I will point you to the best next step.',
                                'confidence' => 0.72,
                                'source_ids' => ['guide:search', 'guide:directory'],
                                'follow_up_questions' => ['Which town should I focus on?'],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson(route('ask-life.store'), [
            'question' => 'Jimmy, what can you help me with?',
        ])
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('sources.0.id', 'guide:search')
            ->assertJsonPath('sources.1.id', 'guide:directory')
            ->assertSee('I am Jimmy', false);

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($body, 'strong sense of honour, integrity, and truth')
                && str_contains($body, 'guide:search')
                && str_contains($body, 'Business directory')
                && str_contains($body, 'platform guide sources');
        });
    }
}
