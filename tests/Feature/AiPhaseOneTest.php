<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_owner_can_save_ai_provider_settings_for_mainstream_providers(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.ai.settings.store'), [
                'provider' => 'nvidia',
                'keys' => [
                    'nvidia' => 'nvapi-test-key',
                    'openrouter' => 'sk-or-test-key',
                    'deepseek' => 'sk-deepseek-test-key',
                    'perplexity' => 'pplx-test-key',
                    'together' => 'together-test-key',
                    'fireworks' => 'fireworks-test-key',
                    'huggingface' => 'hf-test-key',
                ],
                'models' => [
                    'nvidia' => 'meta/llama-3.1-70b-instruct',
                    'openrouter' => 'openai/gpt-oss-120b',
                    'deepseek' => 'deepseek-chat',
                    'perplexity' => 'sonar-pro',
                    'together' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
                    'fireworks' => 'accounts/fireworks/models/llama-v3p1-70b-instruct',
                    'huggingface' => 'meta-llama/Llama-3.1-8B-Instruct',
                ],
                'base_urls' => [
                    'nvidia' => 'https://integrate.api.nvidia.com/v1',
                    'deepseek' => 'https://api.deepseek.com/v1',
                    'perplexity' => 'https://api.perplexity.ai/v1',
                    'together' => 'https://api.together.ai/v1',
                    'fireworks' => 'https://api.fireworks.ai/inference/v1',
                    'huggingface' => 'https://router.huggingface.co/v1',
                ],
                'voice_provider' => 'elevenlabs',
                'voice_key' => 'el-test-key',
                'voice_voice_id' => 'life-voice',
                'voice_english_model' => 'eleven_flash_v2_5',
                'voice_afrikaans_model' => 'eleven_v3',
                'voice_base_url' => 'https://api.elevenlabs.io/v1',
                'voice_output_format' => 'mp3_44100_128',
            ])
            ->assertOk()
            ->assertJsonPath('status.provider', 'nvidia')
            ->assertJsonPath('status.configured', true)
            ->assertJsonPath('voice_status.configured', true);

        $this->assertSame('nvidia', Setting::getValue('ai.provider'));
        $this->assertSame('nvapi-test-key', Setting::getValue('ai.nvidia_api_key'));
        $this->assertSame('meta/llama-3.1-70b-instruct', Setting::getValue('ai.nvidia_model'));
        $this->assertSame('sk-or-test-key', Setting::getValue('ai.openrouter_api_key'));
        $this->assertSame('sk-deepseek-test-key', Setting::getValue('ai.deepseek_api_key'));
        $this->assertSame('sonar-pro', Setting::getValue('ai.perplexity_model'));
        $this->assertSame('https://api.together.ai/v1', Setting::getValue('ai.together_base_url'));
        $this->assertSame('fireworks-test-key', Setting::getValue('ai.fireworks_api_key'));
        $this->assertSame('hf-test-key', Setting::getValue('ai.huggingface_api_key'));
        $this->assertSame('elevenlabs', Setting::getValue('voice.provider'));
        $this->assertSame('el-test-key', Setting::getValue('voice.elevenlabs_api_key'));
        $this->assertSame('life-voice', Setting::getValue('voice.elevenlabs_voice_id'));
        $this->assertSame('eleven_flash_v2_5', Setting::getValue('voice.elevenlabs_english_model'));
        $this->assertSame('eleven_v3', Setting::getValue('voice.elevenlabs_afrikaans_model'));
        $this->assertSame('mp3_44100_128', Setting::getValue('voice.elevenlabs_output_format'));

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tab' => 'ai']))
            ->assertOk()
            ->assertSee('Voice Test')
            ->assertSee('Voice Providers')
            ->assertSee('NVIDIA Speech NIM')
            ->assertSee('NVIDIA Speech NIM testing expects a hosted or local endpoint', false)
            ->assertSee(route('ask-life.speak'), false)
            ->assertSee('data-voice-test', false);
    }

    public function test_listing_description_endpoint_returns_ai_suggestion_and_logs_generation(): void
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
                                'excerpt' => 'Trusted hardware supplies in Reitz.',
                                'description' => 'A practical local hardware store helping Reitz residents with plumbing and roofing supplies.',
                                'tagline' => 'Local hardware help.',
                                'afrikaans_summary' => 'Plaaslike hardewarehulp in Reitz.',
                                'missing_fields' => ['Logo'],
                                'follow_up_message' => 'Please send your logo when you can.',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.listing-description'), [
                'title' => 'Reitz Hardware',
                'rough_notes' => 'Open 15 years, plumbing and roofing supplies.',
                'city' => 'Reitz',
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.excerpt', 'Trusted hardware supplies in Reitz.')
            ->assertJsonPath('suggestion.missing_fields.0', 'Logo');

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'listing_description',
            'provider' => 'openrouter',
            'model' => 'openai/gpt-oss-120b',
            'status' => AiGeneration::STATUS_DRAFT,
        ]);
    }

    public function test_dev_owner_can_save_nvidia_speech_nim_voice_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.ai.settings.store'), [
                'provider' => 'openrouter',
                'voice_provider' => 'nvidia',
                'voice_keys' => [
                    'nvidia' => 'nvapi-voice-test',
                ],
                'voice_voice_ids' => [
                    'nvidia' => 'Magpie-Multilingual.EN-US.Aria',
                ],
                'voice_english_models' => [
                    'nvidia' => 'en-US',
                ],
                'voice_afrikaans_models' => [
                    'nvidia' => 'en-US',
                ],
                'voice_base_urls' => [
                    'nvidia' => 'http://localhost:9000/v1',
                ],
                'voice_output_formats' => [
                    'nvidia' => 'wav_22050',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('voice_status.provider', 'nvidia')
            ->assertJsonPath('voice_status.configured', true);

        $this->assertSame('nvidia', Setting::getValue('voice.provider'));
        $this->assertSame('nvapi-voice-test', Setting::getValue('voice.nvidia_api_key'));
        $this->assertSame('Magpie-Multilingual.EN-US.Aria', Setting::getValue('voice.nvidia_voice_id'));
        $this->assertSame('en-US', Setting::getValue('voice.nvidia_english_model'));
        $this->assertSame('http://localhost:9000/v1', Setting::getValue('voice.nvidia_base_url'));
        $this->assertSame('wav_22050', Setting::getValue('voice.nvidia_output_format'));
    }

    public function test_article_seo_endpoint_returns_structured_metadata(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => 'sk-or-test',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'seo_title' => 'Bethlehem Water Repairs This Week',
                                'seo_description' => 'A clear local update on water repair work affecting Bethlehem residents this week.',
                                'suggested_slug' => 'bethlehem-water-repairs-this-week',
                                'excerpt' => 'Water repair work is affecting parts of Bethlehem this week.',
                                'focus_keywords' => ['Bethlehem', 'water repairs'],
                                'push_teaser' => 'Water repair update for Bethlehem residents.',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.article-seo'), [
                'title' => 'Bethlehem water repairs this week',
                'body' => 'Municipal teams are repairing water infrastructure in Bethlehem this week.',
            ])
            ->assertOk()
            ->assertJsonPath('suggestion.seo_title', 'Bethlehem Water Repairs This Week')
            ->assertJsonPath('suggestion.suggested_slug', 'bethlehem-water-repairs-this-week');
    }

    public function test_fault_categorizer_uses_local_fallback_when_ai_is_not_configured(): void
    {
        config([
            'services.ai.provider' => 'openrouter',
            'services.ai.providers.openrouter.key' => '',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('faults.report.categorize'), [
                'description' => 'Water is spraying from a burst pipe outside the shop.',
                'address_label' => 'Church Street',
            ])
            ->assertOk()
            ->assertJsonPath('source', 'fallback')
            ->assertJsonPath('payload.category', 'burst_pipe');

        Http::assertNothingSent();
    }
}
