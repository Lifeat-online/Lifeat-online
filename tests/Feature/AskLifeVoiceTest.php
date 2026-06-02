<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AskLifeVoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        config([
            'services.voice.provider' => 'elevenlabs',
            'services.voice.providers.elevenlabs.key' => 'el-test',
            'services.voice.providers.elevenlabs.voice_id' => 'voice-test',
            'services.voice.providers.elevenlabs.english_model' => 'eleven_flash_v2_5',
            'services.voice.providers.elevenlabs.afrikaans_model' => 'eleven_v3',
            'services.voice.providers.elevenlabs.base_url' => 'https://api.elevenlabs.io/v1',
            'services.voice.providers.elevenlabs.output_format' => 'mp3_44100_128',
        ]);

        $this->actingAs($this->devOwner());
    }

    public function test_ask_life_voice_generates_and_stores_english_audio(): void
    {
        Http::fake(fn () => Http::response('MP3_BYTES', 200, ['Content-Type' => 'audio/mpeg']));

        $this->postJson(route('ask-life.speak'), [
            'text' => 'There is a mechanic in Harrismith listed on Life@.',
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('cached', false)
            ->assertJsonStructure(['audio_url', 'generation_id']);

        $files = Storage::disk('public')->allFiles('ask-life/voice');
        $this->assertCount(1, $files);
        Storage::disk('public')->assertExists($files[0]);

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life_voice',
            'provider' => 'elevenlabs',
            'model' => 'eleven_flash_v2_5',
            'output_language' => 'en',
            'status' => AiGeneration::STATUS_ACCEPTED,
        ]);

        Http::assertSent(function ($request): bool {
            return Str::contains($request->url(), 'https://api.elevenlabs.io/v1/text-to-speech/voice-test')
                && Str::contains($request->url(), 'output_format=mp3_44100_128')
                && $request->hasHeader('xi-api-key', 'el-test')
                && $request['model_id'] === 'eleven_flash_v2_5'
                && $request['language_code'] === 'en';
        });
    }

    public function test_ask_life_voice_uses_afrikaans_model_for_afrikaans_locale(): void
    {
        Http::fake(fn () => Http::response('MP3_BYTES', 200, ['Content-Type' => 'audio/mpeg']));

        $this->postJson(route('ask-life.speak'), [
            'text' => 'Daar is vandag gebeure in Bethlehem.',
            'locale' => 'af',
        ])
            ->assertOk()
            ->assertJsonPath('locale', 'af');

        Http::assertSent(function ($request): bool {
            return $request['model_id'] === 'eleven_v3'
                && $request['language_code'] === 'af';
        });

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life_voice',
            'model' => 'eleven_v3',
            'output_language' => 'af',
        ]);
    }

    public function test_ask_life_voice_supports_nvidia_speech_nim_for_testing(): void
    {
        config([
            'services.voice.provider' => 'nvidia',
            'services.voice.providers.nvidia.key' => 'nvapi-test',
            'services.voice.providers.nvidia.voice_id' => 'Magpie-Multilingual.EN-US.Aria',
            'services.voice.providers.nvidia.english_model' => 'en-US',
            'services.voice.providers.nvidia.afrikaans_model' => 'en-US',
            'services.voice.providers.nvidia.base_url' => 'http://localhost:9000/v1',
            'services.voice.providers.nvidia.output_format' => 'wav_22050',
            'services.voice.providers.nvidia.type' => 'nvidia_speech_nim',
            'services.voice.providers.nvidia.key_optional' => true,
        ]);

        Http::fake([
            'http://localhost:9000/v1/audio/synthesize' => Http::response('WAV_BYTES', 200, ['Content-Type' => 'audio/wav']),
        ]);

        $this->postJson(route('ask-life.speak'), [
            'text' => 'Hi, I am Jimmy. I can help with Life@.',
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('mime_type', 'audio/wav')
            ->assertJsonPath('cached', false);

        $files = Storage::disk('public')->allFiles('ask-life/voice');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.wav', $files[0]);
        Storage::disk('public')->assertExists($files[0]);

        $this->assertDatabaseHas('ai_generations', [
            'feature_key' => 'ask_life_voice',
            'provider' => 'nvidia',
            'model' => 'en-US',
            'output_language' => 'en',
            'status' => AiGeneration::STATUS_ACCEPTED,
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://localhost:9000/v1/audio/synthesize'
                && $request->hasHeader('Authorization', 'Bearer nvapi-test')
                && Str::contains($request->body(), [
                    'name="language"',
                    'en-US',
                    'name="voice"',
                    'Magpie-Multilingual.EN-US.Aria',
                    'name="sample_rate_hz"',
                    '22050',
                ]);
        });
    }

    public function test_ask_life_voice_allows_nvidia_local_nim_without_api_key(): void
    {
        config([
            'services.voice.provider' => 'nvidia',
            'services.voice.providers.nvidia.key' => '',
            'services.voice.providers.nvidia.voice_id' => 'Magpie-Multilingual.EN-US.Aria',
            'services.voice.providers.nvidia.english_model' => 'en-US',
            'services.voice.providers.nvidia.afrikaans_model' => 'en-US',
            'services.voice.providers.nvidia.base_url' => 'http://localhost:9000/v1',
            'services.voice.providers.nvidia.output_format' => 'wav_22050',
            'services.voice.providers.nvidia.type' => 'nvidia_speech_nim',
            'services.voice.providers.nvidia.key_optional' => true,
        ]);

        Http::fake([
            'http://localhost:9000/v1/audio/synthesize' => Http::response('WAV_BYTES', 200, ['Content-Type' => 'audio/wav']),
        ]);

        $this->postJson(route('ask-life.speak'), [
            'text' => 'Jimmy local voice test.',
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        Http::assertSent(fn ($request): bool => ! $request->hasHeader('Authorization'));
    }

    public function test_ask_life_voice_reuses_cached_audio_for_same_answer(): void
    {
        Http::fake(fn () => Http::response('MP3_BYTES', 200, ['Content-Type' => 'audio/mpeg']));

        $payload = [
            'text' => 'A short Jimmy answer to play twice.',
            'locale' => 'en',
        ];

        $this->postJson(route('ask-life.speak'), $payload)
            ->assertOk()
            ->assertJsonPath('cached', false);

        $this->postJson(route('ask-life.speak'), $payload)
            ->assertOk()
            ->assertJsonPath('cached', true);

        Http::assertSentCount(1);
        $this->assertCount(1, Storage::disk('public')->allFiles('ask-life/voice'));
    }

    public function test_ask_life_voice_reports_missing_configuration(): void
    {
        config(['services.voice.providers.elevenlabs.key' => '']);

        $this->postJson(route('ask-life.speak'), [
            'text' => 'Read this aloud.',
            'locale' => 'en',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }

    private function devOwner(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);
    }
}
