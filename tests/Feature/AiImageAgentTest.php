<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\Category;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiImageAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_agent_command_generates_labelled_openai_illustration_for_jimmy_draft(): void
    {
        Storage::fake('public');
        $this->configureOpenAiImages();

        $article = $this->jimmyDraftArticle();
        $this->fakeOpenAiImage();

        $this->artisan('life:images:generate --limit=5')
            ->expectsOutputToContain('Illustration generated: Bethlehem water repair timeline')
            ->expectsOutputToContain('Image Agent complete: 1 generated, 0 failed, 0 skipped.')
            ->assertExitCode(0);

        $article->refresh();

        $this->assertSame('openai', $article->featured_image_provider);
        $this->assertSame('gpt-image-1', $article->featured_image_model);
        $this->assertTrue($article->featured_image_is_ai_generated);
        $this->assertSame('AI-generated illustration for this article.', $article->featured_image_caption);
        $this->assertStringStartsWith('articles/ai-generated/', $article->featured_image);
        Storage::disk('public')->assertExists($article->featured_image);

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'article_image',
            'source_type' => Article::class,
            'source_id' => $article->id,
            'provider' => 'openai',
            'model' => 'gpt-image-1',
            'status' => AiGeneration::STATUS_ACCEPTED,
        ]);
    }

    public function test_image_agent_supports_openrouter_as_testing_image_provider(): void
    {
        Storage::fake('public');
        $this->configureOpenRouterImages();

        $article = $this->jimmyDraftArticle([
            'title' => 'Reitz school fundraiser',
            'slug' => 'reitz-school-fundraiser',
        ]);
        $this->fakeOpenRouterImage();

        $this->artisan('life:images:generate --limit=5')
            ->expectsOutputToContain('Illustration generated: Reitz school fundraiser')
            ->assertExitCode(0);

        $article->refresh();

        $this->assertSame('openrouter', $article->featured_image_provider);
        $this->assertSame('google/gemini-2.5-flash-image', $article->featured_image_model);
        $this->assertTrue($article->featured_image_is_ai_generated);
        Storage::disk('public')->assertExists($article->featured_image);
    }

    public function test_image_agent_supports_gemini_image_provider(): void
    {
        Storage::fake('public');
        $this->configureGeminiImages();

        $article = $this->jimmyDraftArticle([
            'title' => 'Clarens market weekend',
            'slug' => 'clarens-market-weekend',
        ]);
        $this->fakeGeminiImage();

        $this->artisan('life:images:generate --limit=5')
            ->expectsOutputToContain('Illustration generated: Clarens market weekend')
            ->assertExitCode(0);

        $article->refresh();

        $this->assertSame('google', $article->featured_image_provider);
        $this->assertSame('gemini-2.5-flash-image', $article->featured_image_model);
        $this->assertTrue($article->featured_image_is_ai_generated);
        Storage::disk('public')->assertExists($article->featured_image);
    }

    public function test_image_agent_supports_nvidia_nim_image_provider(): void
    {
        Storage::fake('public');
        $this->configureNvidiaImages();

        $article = $this->jimmyDraftArticle([
            'title' => 'Harrismith community meeting',
            'slug' => 'harrismith-community-meeting',
        ]);
        $this->fakeNvidiaImage();

        $this->artisan('life:images:generate --limit=5')
            ->expectsOutputToContain('Illustration generated: Harrismith community meeting')
            ->assertExitCode(0);

        $article->refresh();

        $this->assertSame('nvidia', $article->featured_image_provider);
        $this->assertSame('black-forest-labs/flux.1-dev', $article->featured_image_model);
        $this->assertTrue($article->featured_image_is_ai_generated);
        Storage::disk('public')->assertExists($article->featured_image);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer nvapi-test')
            && $request->url() === 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev'
            && $request['width'] === 1024
            && $request['height'] === 1024
            && $request['samples'] === 1);
    }

    public function test_image_agent_tries_nvidia_fallback_model_before_other_image_provider(): void
    {
        Storage::fake('public');
        $this->configureNvidiaImages();
        config([
            'services.ai_image.fallback_providers' => ['openrouter'],
            'services.ai_image.providers.nvidia.fallback_models' => ['black-forest-labs/flux.1-schnell'],
            'services.ai_image.providers.openrouter.key' => 'sk-or-test',
            'services.ai_image.providers.openrouter.model' => 'google/gemini-2.5-flash-image',
            'services.ai_image.providers.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        ]);

        $article = $this->jimmyDraftArticle([
            'title' => 'Ficksburg library upgrade',
            'slug' => 'ficksburg-library-upgrade',
        ]);
        $urls = [];

        Http::fake(function ($request) use (&$urls) {
            $urls[] = $request->url();

            if ($request->url() === 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev') {
                throw new ConnectionException('cURL error 28: Operation timed out after 120000 milliseconds with 0 bytes received');
            }

            if ($request->url() === 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-schnell') {
                return Http::response([
                    'artifacts' => [
                        [
                            'base64' => base64_encode($this->pngBytes()),
                            'mime_type' => 'image/png',
                        ],
                    ],
                ]);
            }

            return Http::response(['error' => 'Provider fallback should not be called.'], 500);
        });

        $result = app(\App\Services\AiImageService::class)->generateForArticle($article);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('black-forest-labs/flux.1-schnell', $result['message']);
        $this->assertSame([
            'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev',
            'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-schnell',
        ], $urls);

        $article->refresh();

        $this->assertSame('nvidia', $article->featured_image_provider);
        $this->assertSame('black-forest-labs/flux.1-schnell', $article->featured_image_model);
        Storage::disk('public')->assertExists($article->featured_image);
    }

    public function test_admin_can_generate_article_image_from_article_editor(): void
    {
        Storage::fake('public');
        $this->configureOpenAiImages();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $article = $this->jimmyDraftArticle();
        $this->fakeOpenAiImage();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.articles.ai-image', $article), [
                'force' => false,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Image Agent illustration generated.')
            ->assertJsonStructure(['image_url']);

        $this->assertStringStartsWith('/media/articles/ai-generated/', $response->json('image_url'));

        $article->refresh();

        $this->assertTrue($article->featured_image_is_ai_generated);
        Storage::disk('public')->assertExists($article->featured_image);
    }

    public function test_admin_image_endpoint_returns_actionable_error_when_provider_bytes_are_not_an_image(): void
    {
        Storage::fake('public');
        $this->configureOpenAiImages();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $article = $this->jimmyDraftArticle();

        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    ['b64_json' => base64_encode('not-image-bytes')],
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.articles.ai-image', $article), [
                'force' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'OpenAI Images returned data, but it was not a displayable image.');

        $this->assertNull($article->fresh()->featured_image);
    }

    public function test_public_storage_fallback_route_serves_generated_article_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/ai-generated/example.png', $this->pngBytes());

        $response = $this->get('/media/articles/ai-generated/example.png');

        $response->assertOk();
        $this->assertStringContainsString('image/png', (string) $response->baseResponse->headers->get('content-type'));
    }

    private function jimmyDraftArticle(array $overrides = []): Article
    {
        $category = Category::create([
            'type' => 'article',
            'name' => 'Local News',
            'slug' => 'local-news',
        ]);
        $source = ResearchSource::create([
            'name' => 'Google News: Bethlehem',
            'slug' => 'google-news-bethlehem-'.uniqid(),
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
        $brief = ArticleBrief::create([
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
            'status' => ArticleBrief::STATUS_DRAFTED,
        ]);

        $article = Article::create([
            'article_brief_id' => $brief->id,
            'title' => 'Bethlehem water repair timeline',
            'slug' => 'bethlehem-water-repair-timeline',
            'excerpt' => 'A local update on repair work.',
            'body' => 'Dihlabeng repair teams are expected to work on Bethlehem water lines this week.',
            'source_locale' => 'en',
            'status' => 'draft',
            'featured_image_prompt' => 'Editorial illustration of water repair teams in a small Free State town.',
            ...$overrides,
        ]);
        $article->categories()->sync([$category->id]);

        return $article;
    }

    private function configureOpenAiImages(): void
    {
        config([
            'services.ai_image.provider' => 'openai',
            'services.ai_image.fallback_providers' => [],
            'services.ai_image.providers.openai.key' => 'sk-openai-test',
            'services.ai_image.providers.openai.model' => 'gpt-image-1',
            'services.ai_image.providers.openai.base_url' => 'https://api.openai.com/v1',
            'services.ai_image.providers.openai.size' => '1024x1024',
        ]);
    }

    private function configureOpenRouterImages(): void
    {
        config([
            'services.ai_image.provider' => 'openrouter',
            'services.ai_image.fallback_providers' => [],
            'services.ai_image.providers.openrouter.key' => 'sk-or-test',
            'services.ai_image.providers.openrouter.model' => 'google/gemini-2.5-flash-image',
            'services.ai_image.providers.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.ai_image.providers.openrouter.size' => '1K',
        ]);
    }

    private function configureGeminiImages(): void
    {
        config([
            'services.ai_image.provider' => 'google',
            'services.ai_image.fallback_providers' => [],
            'services.ai_image.providers.google.key' => 'gemini-test',
            'services.ai_image.providers.google.model' => 'gemini-2.5-flash-image',
            'services.ai_image.providers.google.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'services.ai_image.providers.google.size' => '1024x1024',
        ]);
    }

    private function configureNvidiaImages(): void
    {
        config([
            'services.ai_image.provider' => 'nvidia',
            'services.ai_image.fallback_providers' => [],
            'services.ai_image.providers.nvidia.key' => 'nvapi-test',
            'services.ai_image.providers.nvidia.model' => 'black-forest-labs/flux.1-dev',
            'services.ai_image.providers.nvidia.base_url' => 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev',
            'services.ai_image.providers.nvidia.size' => '1024x1024',
            'services.ai_image.providers.nvidia.type' => 'nvidia_nim_infer',
        ]);
    }

    private function fakeOpenAiImage(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    ['b64_json' => base64_encode($this->pngBytes())],
                ],
            ]),
        ]);
    }

    private function fakeOpenRouterImage(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'images' => [
                                [
                                    'image_url' => [
                                        'url' => 'data:image/png;base64,'.base64_encode($this->pngBytes()),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    private function fakeGeminiImage(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => base64_encode($this->pngBytes()),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    private function fakeNvidiaImage(): void
    {
        Http::fake([
            'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev' => Http::response([
                'artifacts' => [
                    [
                        'base64' => base64_encode($this->pngBytes()),
                        'mime_type' => 'image/png',
                    ],
                ],
            ]),
        ]);
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
    }
}
