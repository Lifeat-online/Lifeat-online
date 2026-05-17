<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Article;
use App\Models\InterfaceTranslation;
use App\Models\User;
use App\Services\OpenRouterTranslationService;
use App\Services\VapidKeySetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class DevToolsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('DEV_TOOLS_ENABLED');
        putenv('DEV_TEST_RUNNER_ENABLED');

        parent::tearDown();
    }

    public function test_super_admin_can_use_dev_test_runner_in_testing_environment(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.tests.run'), ['suite' => 'Bogus'])
            ->assertUnprocessable();
    }

    public function test_dev_test_runner_is_blocked_in_production_unless_explicitly_enabled(): void
    {
        config(['app.env' => 'production']);
        putenv('DEV_TOOLS_ENABLED=false');

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.tests.run'), ['suite' => 'Unit'])
            ->assertForbidden();
    }

    public function test_dev_tab_is_hidden_from_non_owner_even_when_dev_tools_are_enabled(): void
    {
        config(['app.env' => 'production']);
        putenv('DEV_TOOLS_ENABLED=true');

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'other-admin@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Developer Control Center');
        $response->assertDontSee('Testing Area');
    }

    public function test_test_runner_panel_is_hidden_when_runner_is_disabled_in_production(): void
    {
        config(['app.env' => 'production']);
        putenv('DEV_TOOLS_ENABLED=true');
        putenv('DEV_TEST_RUNNER_ENABLED=false');

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Developer Control Center');
        $response->assertDontSee('Testing Area');
        $response->assertDontSee(route('dev.tests.run'));
    }

    public function test_dev_owner_can_enable_vapid_keys_from_dev_endpoint(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->mock(VapidKeySetupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('enable')
                ->once()
                ->andReturn([
                    'configured' => true,
                    'public_key_configured' => true,
                    'private_key_configured' => true,
                    'subject' => 'https://example.test',
                    'env_writable' => true,
                    'changed' => true,
                    'message' => 'VAPID keys were generated and saved to .env.',
                ]);
        });

        $this->actingAs($admin)
            ->postJson(route('dev.webpush.vapid.enable'))
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('changed', true);
    }

    public function test_non_owner_cannot_enable_vapid_keys(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'other-admin@example.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.webpush.vapid.enable'))
            ->assertForbidden();
    }

    public function test_dev_owner_can_save_openrouter_translation_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.translations.key.store'), [
                'provider' => 'openrouter',
                'openrouter_api_key' => 'sk-or-test-translation-key',
                'openrouter_model' => 'google/gemma-4-31b-it:free',
            ])
            ->assertOk()
            ->assertJsonPath('provider', 'openrouter')
            ->assertJsonPath('configured', true)
            ->assertJsonPath('source', 'Settings')
            ->assertJsonPath('model', 'google/gemma-4-31b-it:free');

        $this->assertDatabaseHas('settings', [
            'key' => 'translation.openrouter_api_key',
            'type' => 'secret',
            'group' => 'translations',
        ]);

        $this->assertSame('sk-or-test-translation-key', Setting::getValue('translation.openrouter_api_key'));
        $this->assertTrue(app(OpenRouterTranslationService::class)->configured());
    }

    public function test_dev_owner_can_save_azure_translation_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.translations.key.store'), [
                'provider' => 'azure',
                'azure_api_key' => 'azure-test-translation-key',
                'azure_region' => 'southafricanorth',
            ])
            ->assertOk()
            ->assertJsonPath('provider', 'azure')
            ->assertJsonPath('configured', true)
            ->assertJsonPath('source', 'Settings')
            ->assertJsonPath('model', 'azure-translator')
            ->assertJsonPath('azure_region', 'southafricanorth');

        $this->assertSame('azure-test-translation-key', Setting::getValue('translation.azure_api_key'));
        $this->assertSame('southafricanorth', Setting::getValue('translation.azure_region'));
        $this->assertSame('azure', Setting::getValue('translation.provider'));
    }

    public function test_dev_owner_can_save_google_translation_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.translations.key.store'), [
                'provider' => 'google',
                'google_api_key' => 'google-test-translation-key',
            ])
            ->assertOk()
            ->assertJsonPath('provider', 'google')
            ->assertJsonPath('configured', true)
            ->assertJsonPath('source', 'Settings')
            ->assertJsonPath('model', 'google-cloud-translation-basic-v2');

        $this->assertSame('google-test-translation-key', Setting::getValue('translation.google_api_key'));
        $this->assertSame('google', Setting::getValue('translation.provider'));
    }

    public function test_non_owner_cannot_save_openrouter_translation_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'other-admin@example.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.translations.key.store'), [
                'provider' => 'openrouter',
                'openrouter_api_key' => 'sk-or-test-translation-key',
            ])
            ->assertForbidden();
    }

    public function test_translation_preview_returns_json_message_when_provider_is_missing(): void
    {
        config(['services.openrouter.key' => '']);
        Http::fake();

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.translations.preview'), [
                'text' => 'Translate this preview.',
                'target_locale' => 'af',
            ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Translation provider is not configured or returned no text.');

        Http::assertNothingSent();
    }

    public function test_dev_owner_can_run_article_translation_batch(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Batch Translation',
            'slug' => 'batch-translation',
            'excerpt' => 'Short batch excerpt',
            'body' => 'This article should be picked up by the translation batch.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock) use ($article): void {
            $mock->shouldReceive('translateModel')
                ->once()
                ->withArgs(fn (Article $target, string $locale, bool $force): bool => $target->is($article) && $locale === 'af' && $force === false)
                ->andReturn(['ok' => true, 'message' => 'Translation saved.']);
        });

        $this->actingAs($admin)
            ->postJson(route('dev.translations.batch'), [
                'section' => 'articles',
                'limit' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('processed', 1)
            ->assertJsonPath('translated', 1);
    }

    public function test_translation_batch_response_includes_provider_error_details(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $article = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Provider Failure',
            'slug' => 'provider-failure',
            'excerpt' => 'Short excerpt',
            'body' => 'This article will fail translation.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock) use ($article): void {
            $mock->shouldReceive('translateModel')
                ->once()
                ->withArgs(fn (Article $target, string $locale, bool $force): bool => $target->is($article) && $locale === 'af' && $force === false)
                ->andReturn(['ok' => false, 'message' => 'OpenRouter returned 429: Rate limit exceeded.']);
            $mock->shouldReceive('wasRateLimited')
                ->twice()
                ->andReturnFalse();
        });

        $this->actingAs($admin)
            ->postJson(route('dev.translations.batch'), [
                'section' => 'articles',
                'limit' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('processed', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('errors.0', 'OpenRouter returned 429: Rate limit exceeded.')
            ->assertJsonFragment([
                'message' => 'Processed 1 translation targets: 0 translated, 0 current, 1 failed. First issue: OpenRouter returned 429: Rate limit exceeded.',
            ]);
    }

    public function test_translation_batch_stops_early_when_openrouter_rate_limits(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $firstArticle = Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Rate Limited One',
            'slug' => 'rate-limited-one',
            'excerpt' => 'Short excerpt',
            'body' => 'This article will hit rate limits.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Article::create([
            'user_id' => User::factory()->create(['role' => 'writer'])->id,
            'title' => 'Rate Limited Two',
            'slug' => 'rate-limited-two',
            'excerpt' => 'Short excerpt',
            'body' => 'This article should not be attempted in the same run.',
            'source_locale' => 'en',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->mock(OpenRouterTranslationService::class, function (MockInterface $mock) use ($firstArticle): void {
            $mock->shouldReceive('translateModel')
                ->once()
                ->withArgs(fn (Article $target, string $locale, bool $force): bool => $target->is($firstArticle) && $locale === 'af' && $force === false)
                ->andReturn(['ok' => false, 'message' => 'OpenRouter returned 429: Provider rate limit exceeded.']);
            $mock->shouldReceive('wasRateLimited')
                ->twice()
                ->andReturnTrue();
        });

        $this->actingAs($admin)
            ->postJson(route('dev.translations.batch'), [
                'section' => 'articles',
                'limit' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('processed', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('halted', true)
            ->assertJsonPath('sections.0.halted', true)
            ->assertJsonFragment([
                'message' => 'Processed 1 translation targets: 0 translated, 0 current, 1 failed. First issue: OpenRouter returned 429: Provider rate limit exceeded. Wait a minute, then retry with Items per run set to 1-3, or switch to a paid/non-free OpenRouter model for bulk translation. Batch stopped early to avoid repeated failed provider calls.',
            ]);
    }

    public function test_openrouter_translation_uses_structured_outputs_and_app_headers(): void
    {
        config([
            'app.name' => 'Life Platform',
            'app.url' => 'https://lifeat.test',
            'services.openrouter.key' => 'sk-or-test',
        ]);

        Http::fake(function ($request) {
            $payload = $request->data();

            $this->assertSame('https://openrouter.ai/api/v1/chat/completions', (string) $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer sk-or-test'));
            $this->assertTrue($request->hasHeader('HTTP-Referer', 'https://lifeat.test'));
            $this->assertTrue($request->hasHeader('X-OpenRouter-Title', 'Life Platform'));
            $this->assertSame('json_schema', $payload['response_format']['type']);
            $this->assertSame('platform_translation', $payload['response_format']['json_schema']['name']);
            $this->assertTrue($payload['structured_outputs']);
            $this->assertSame(['title'], $payload['response_format']['json_schema']['schema']['required']);

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['title' => 'Gemeenskapsnuus']),
                        ],
                    ],
                ],
            ]);
        });

        $translated = app(OpenRouterTranslationService::class)->translateContent([
            'title' => 'Community News',
        ], 'af');

        $this->assertSame(['title' => 'Gemeenskapsnuus'], $translated);
    }

    public function test_azure_translator_is_used_as_primary_translation_provider(): void
    {
        config([
            'services.translation.provider' => 'azure',
            'services.azure_translator.key' => 'azure-test-key',
            'services.azure_translator.region' => 'southafricanorth',
            'services.azure_translator.endpoint' => 'https://api.cognitive.microsofttranslator.com',
        ]);

        Http::fake(function ($request) {
            $this->assertStringStartsWith('https://api.cognitive.microsofttranslator.com/translate?', (string) $request->url());
            $this->assertStringContainsString('api-version=3.0', (string) $request->url());
            $this->assertStringContainsString('to=af', (string) $request->url());
            $this->assertStringContainsString('from=en', (string) $request->url());
            $this->assertTrue($request->hasHeader('Ocp-Apim-Subscription-Key', 'azure-test-key'));
            $this->assertTrue($request->hasHeader('Ocp-Apim-Subscription-Region', 'southafricanorth'));
            $this->assertSame([
                ['Text' => 'Community News'],
            ], $request->data());

            return Http::response([
                [
                    'translations' => [
                        ['text' => 'Gemeenskapsnuus', 'to' => 'af'],
                    ],
                ],
            ]);
        });

        $translator = app(OpenRouterTranslationService::class);
        $translated = $translator->translateContent([
            'title' => 'Community News',
        ], 'af', 'en');

        $this->assertSame(['title' => 'Gemeenskapsnuus'], $translated);
        $this->assertSame('azure', $translator->providerUsed());
        $this->assertSame('azure-translator', $translator->modelUsed());
    }

    public function test_google_translate_is_used_as_primary_translation_provider(): void
    {
        config([
            'services.translation.provider' => 'google',
            'services.google_translate.key' => 'google-test-key',
            'services.google_translate.endpoint' => 'https://translation.googleapis.com/language/translate/v2',
        ]);

        Http::fake(function ($request) {
            $this->assertStringStartsWith('https://translation.googleapis.com/language/translate/v2?', (string) $request->url());
            $this->assertStringContainsString('key=google-test-key', (string) $request->url());
            $this->assertSame([
                'q' => ['Community News'],
                'target' => 'af',
                'format' => 'html',
                'source' => 'en',
            ], $request->data());

            return Http::response([
                'data' => [
                    'translations' => [
                        ['translatedText' => 'Gemeenskapsnuus &amp; gebeure'],
                    ],
                ],
            ]);
        });

        $translator = app(OpenRouterTranslationService::class);
        $translated = $translator->translateContent([
            'title' => 'Community News',
        ], 'af', 'en');

        $this->assertSame(['title' => 'Gemeenskapsnuus & gebeure'], $translated);
        $this->assertSame('google', $translator->providerUsed());
        $this->assertSame('google-cloud-translation-basic-v2', $translator->modelUsed());
    }

    public function test_openrouter_translation_falls_back_to_json_mode_when_schema_is_rejected(): void
    {
        config([
            'services.openrouter.key' => 'sk-or-test',
        ]);

        Http::fakeSequence()
            ->push(['error' => ['message' => 'response_format json_schema is not supported']], 400)
            ->push([
                'choices' => [
                    [
                        'message' => [
                            'content' => '```json'.PHP_EOL.json_encode(['title' => 'Gemeenskapsnuus']).PHP_EOL.'```',
                        ],
                    ],
                ],
            ]);

        $translated = app(OpenRouterTranslationService::class)->translateContent([
            'title' => 'Community News',
        ], 'af');

        $this->assertSame(['title' => 'Gemeenskapsnuus'], $translated);

        $requests = Http::recorded();
        $this->assertSame('json_schema', $requests[0][0]->data()['response_format']['type']);
        $this->assertSame('json_object', $requests[1][0]->data()['response_format']['type']);
    }

    public function test_openrouter_translation_extracts_json_object_from_messy_model_response(): void
    {
        config([
            'services.openrouter.key' => 'sk-or-test',
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Here is the translation: {"title":"Gemeenskapsnuus"} Thanks.',
                        ],
                    ],
                ],
            ]),
        ]);

        $translated = app(OpenRouterTranslationService::class)->translateContent([
            'title' => 'Community News',
        ], 'af');

        $this->assertSame(['title' => 'Gemeenskapsnuus'], $translated);
    }

    public function test_openrouter_translation_reports_invalid_json_preview(): void
    {
        config([
            'services.openrouter.key' => 'sk-or-test',
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'I translated it, but not as JSON.',
                        ],
                    ],
                ],
            ]),
        ]);

        $translator = app(OpenRouterTranslationService::class);

        $this->assertNull($translator->translateContent([
            'title' => 'Community News',
        ], 'af'));
        $this->assertStringContainsString('OpenRouter returned invalid JSON', $translator->lastFailureMessage());
        $this->assertStringContainsString('I translated it, but not as JSON.', $translator->lastFailureMessage());
    }

    public function test_interface_translations_are_applied_to_rendered_platform_html(): void
    {
        InterfaceTranslation::create([
            'locale' => 'af',
            'source_hash' => hash('sha256', 'Login'),
            'source_text' => 'Login',
            'translated_text' => 'Teken in',
            'provider' => 'test',
            'model' => 'test',
            'translated_at' => now(),
        ]);

        $response = $this
            ->withSession(['locale' => 'af'])
            ->get(route('home'));

        $response->assertOk();
        $response->assertSee('Teken in');
    }
}
