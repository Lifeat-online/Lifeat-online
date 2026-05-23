<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiArticleTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_ai_article_translation_into_content_translations(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $article = Article::create([
            'user_id' => $admin->id,
            'title' => 'Bethlehem Water Repairs',
            'slug' => 'bethlehem-water-repairs',
            'excerpt' => 'Water repairs are planned this week.',
            'body' => 'Municipal teams will repair water lines in Bethlehem this week.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'translated_fields' => [
                                'title' => 'Bethlehem Waterherstelwerk',
                                'excerpt' => 'Waterherstelwerk word hierdie week beplan.',
                                'body' => 'Munisipale spanne sal hierdie week waterlyne in Bethlehem herstel.',
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.article-translation', $article), [
                'target_locale' => 'af',
            ])
            ->assertOk()
            ->assertJsonPath('translation.locale', 'af')
            ->assertJsonPath('translation.content.title', 'Bethlehem Waterherstelwerk')
            ->assertJsonPath('translation.provider', 'openrouter');

        $this->assertDatabaseHas('content_translations', [
            'translatable_type' => Article::class,
            'translatable_id' => $article->id,
            'locale' => 'af',
            'provider' => 'openrouter',
            'model' => 'openai/gpt-oss-120b',
        ]);

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'article_translation',
            'source_type' => Article::class,
            'source_id' => $article->id,
            'status' => AiGeneration::STATUS_ACCEPTED,
            'output_language' => 'af',
        ]);
    }

    public function test_current_ai_article_translation_is_not_regenerated_without_force(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $article = Article::create([
            'user_id' => $admin->id,
            'title' => 'Clarens Market',
            'slug' => 'clarens-market',
            'excerpt' => 'A local market opens this weekend.',
            'body' => 'The market will feature local makers and food stalls.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $article->contentTranslations()->create([
            'locale' => 'af',
            'content' => [
                'title' => 'Clarens Mark',
                'excerpt' => 'Plaaslike mark hierdie naweek.',
                'body' => 'Die mark bied plaaslike makers en kosstalletjies.',
            ],
            'source_locale' => 'en',
            'source_hash' => $article->contentSourceHash(),
            'provider' => 'openrouter',
            'model' => 'openai/gpt-oss-120b',
            'translated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.article-translation', $article), [
                'target_locale' => 'af',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'AI translation is already current.')
            ->assertJsonPath('translation.content.title', 'Clarens Mark');

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_generations', 0);
    }

    public function test_article_translation_rejects_source_locale_as_target(): void
    {
        $this->configureOpenRouter();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $article = Article::create([
            'user_id' => $admin->id,
            'title' => 'Local Story',
            'slug' => 'local-story',
            'body' => 'Local story body.',
            'source_locale' => 'en',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.article-translation', $article), [
                'target_locale' => 'en',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Target language must be different from the article source language.');
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
