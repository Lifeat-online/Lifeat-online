<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Article;
use App\Models\User;
use App\Services\OpenRouterTranslationService;
use App\Services\VapidKeySetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'api_key' => 'sk-or-test-translation-key',
                'model' => 'google/gemma-4-31b-it:free',
            ])
            ->assertOk()
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

    public function test_non_owner_cannot_save_openrouter_translation_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'other-admin@example.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.translations.key.store'), [
                'api_key' => 'sk-or-test-translation-key',
            ])
            ->assertForbidden();
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
}
