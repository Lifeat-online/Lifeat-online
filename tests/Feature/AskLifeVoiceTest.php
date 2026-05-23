<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
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

    public function test_ask_life_voice_reuses_cached_audio_for_same_answer(): void
    {
        Http::fake(fn () => Http::response('MP3_BYTES', 200, ['Content-Type' => 'audio/mpeg']));

        $payload = [
            'text' => 'A short Ask Life answer to play twice.',
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
}
