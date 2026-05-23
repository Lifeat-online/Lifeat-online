<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiBudgetService;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_owner_can_view_ai_operations_log_and_prompt_templates(): void
    {
        $admin = $this->devOwner();

        AiGeneration::create([
            'feature_key' => 'ask_life',
            'provider' => 'openrouter',
            'model' => 'google/gemini-2.5-flash:free',
            'prompt_version' => 'ask_life_v1',
            'input_hash' => hash('sha256', 'question'),
            'input_summary' => 'question: mechanic in Bethlehem',
            'input_payload' => ['question' => 'mechanic in Bethlehem'],
            'output_payload' => ['answer' => 'Try this local listing.'],
            'status' => AiGeneration::STATUS_DRAFT,
            'cost_estimate' => 1.23,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-operations.index'))
            ->assertOk()
            ->assertSee('AI Operations')
            ->assertSee('Generation Log')
            ->assertSee('Prompt Templates')
            ->assertSee('Cost Tracking')
            ->assertSee('R 1.230000')
            ->assertSee('Monthly Budget')
            ->assertSee('ask life')
            ->assertSee('google/gemini-2.5-flash:free')
            ->assertSee('Listing Description');
    }

    public function test_dev_owner_can_override_and_reset_prompt_template(): void
    {
        $admin = $this->devOwner();

        $this->actingAs($admin)
            ->put(route('admin.ai-operations.prompts.update', 'ask_life'), [
                'system' => 'Custom Jimmy system prompt. Return only JSON.',
                'version' => 'ask_life_custom_v1',
                'output_language' => 'af',
            ])
            ->assertRedirect();

        $this->assertSame('Custom Jimmy system prompt. Return only JSON.', Setting::getValue('ai_prompt.ask_life.system'));
        $this->assertSame('ask_life_custom_v1', Setting::getValue('ai_prompt.ask_life.version'));

        $prompt = app(AiPromptCatalog::class)->get('ask_life');
        $this->assertSame('Custom Jimmy system prompt. Return only JSON.', $prompt['system']);
        $this->assertSame('ask_life_custom_v1', $prompt['version']);
        $this->assertSame('af', $prompt['output_language']);
        $this->assertTrue($prompt['is_custom']);

        $this->actingAs($admin)
            ->delete(route('admin.ai-operations.prompts.reset', 'ask_life'))
            ->assertRedirect();

        $this->assertNull(Setting::getValue('ai_prompt.ask_life.system'));
        $this->assertFalse(app(AiPromptCatalog::class)->get('ask_life')['is_custom']);
    }

    public function test_dev_owner_can_save_monthly_ai_budget_settings(): void
    {
        $admin = $this->devOwner();

        $this->actingAs($admin)
            ->put(route('admin.ai-operations.budget.update'), [
                'monthly_limit_zar' => '250.50',
                'warning_percent' => '70',
                'hard_stop_enabled' => '1',
                'exempt_features' => 'settings_test, ask_life',
            ])
            ->assertRedirect();

        $this->assertSame('250.5', Setting::getValue('ai_budget.monthly_limit_zar'));
        $this->assertSame('70', Setting::getValue('ai_budget.warning_percent'));
        $this->assertSame('1', Setting::getValue('ai_budget.hard_stop_enabled'));
        $this->assertSame('settings_test, ask_life', Setting::getValue('ai_budget.exempt_features'));

        $status = app(AiBudgetService::class)->status();
        $this->assertSame(250.5, $status['limit']);
        $this->assertTrue($status['hard_stop_enabled']);
        $this->assertContains('ask_life', $status['exempt_features']);
    }

    public function test_hard_stop_blocks_non_exempt_voice_generation_without_calling_provider(): void
    {
        Storage::fake('public');
        Http::fake(fn () => Http::response('MP3_BYTES', 200, ['Content-Type' => 'audio/mpeg']));

        config([
            'services.voice.provider' => 'elevenlabs',
            'services.voice.providers.elevenlabs.key' => 'el-test',
            'services.voice.providers.elevenlabs.voice_id' => 'voice-test',
            'services.voice.providers.elevenlabs.english_model' => 'eleven_flash_v2_5',
            'services.voice.providers.elevenlabs.afrikaans_model' => 'eleven_v3',
            'services.voice.providers.elevenlabs.base_url' => 'https://api.elevenlabs.io/v1',
            'services.voice.providers.elevenlabs.output_format' => 'mp3_44100_128',
        ]);

        Setting::create(['key' => 'ai_budget.monthly_limit_zar', 'value' => '1', 'type' => 'number', 'group' => 'ai_budget']);
        Setting::create(['key' => 'ai_budget.warning_percent', 'value' => '80', 'type' => 'number', 'group' => 'ai_budget']);
        Setting::create(['key' => 'ai_budget.hard_stop_enabled', 'value' => '1', 'type' => 'string', 'group' => 'ai_budget']);

        AiGeneration::create([
            'feature_key' => 'article_image',
            'provider' => 'openai',
            'model' => 'gpt-image-1',
            'input_hash' => hash('sha256', 'spent'),
            'input_summary' => 'Previous spend',
            'status' => AiGeneration::STATUS_ACCEPTED,
            'cost_estimate' => 1.25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('ask-life.speak'), [
            'text' => 'Read this answer aloud.',
            'locale' => 'en',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['generation_id']);

        Http::assertNothingSent();
        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life_voice',
            'status' => AiGeneration::STATUS_FAILED,
            'cost_estimate' => 0,
        ]);
    }

    public function test_dev_owner_can_retry_stored_voice_generation(): void
    {
        Storage::fake('public');
        Http::fake(fn () => Http::response('MP3_BYTES', 200, ['Content-Type' => 'audio/mpeg']));

        config([
            'services.voice.provider' => 'elevenlabs',
            'services.voice.providers.elevenlabs.key' => 'el-test',
            'services.voice.providers.elevenlabs.voice_id' => 'voice-test',
            'services.voice.providers.elevenlabs.english_model' => 'eleven_flash_v2_5',
            'services.voice.providers.elevenlabs.afrikaans_model' => 'eleven_v3',
            'services.voice.providers.elevenlabs.base_url' => 'https://api.elevenlabs.io/v1',
            'services.voice.providers.elevenlabs.output_format' => 'mp3_44100_128',
        ]);

        $failed = AiGeneration::create([
            'feature_key' => 'ask_life_voice',
            'provider' => 'elevenlabs',
            'model' => 'eleven_flash_v2_5',
            'prompt_version' => 'ask_life_voice_v1',
            'input_hash' => hash('sha256', 'voice'),
            'input_summary' => 'Read this Jimmy answer aloud.',
            'input_payload' => [
                'text' => 'Read this Jimmy answer aloud.',
                'locale' => 'en',
            ],
            'status' => AiGeneration::STATUS_FAILED,
            'error_message' => 'Previous provider timeout.',
        ]);

        $this->actingAs($this->devOwner())
            ->post(route('admin.ai-operations.generations.retry', $failed))
            ->assertRedirect();

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life_voice',
            'provider' => 'elevenlabs',
            'model' => 'eleven_flash_v2_5',
            'status' => AiGeneration::STATUS_ACCEPTED,
            'retry_of_id' => $failed->id,
        ]);

        $retry = AiGeneration::query()
            ->where('retry_of_id', $failed->id)
            ->firstOrFail();

        $this->assertGreaterThan(0, (float) $retry->cost_estimate);

        $this->assertCount(1, Storage::disk('public')->allFiles('ask-life/voice'));
        Http::assertSentCount(1);
    }

    private function devOwner(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);
    }
}
