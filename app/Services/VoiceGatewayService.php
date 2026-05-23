<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class VoiceGatewayService
{
    public function __construct(
        private readonly AiCostEstimatorService $costs,
        private readonly AiBudgetService $budget,
    ) {
    }

    public function providers(): array
    {
        return collect((array) config('services.voice.providers', []))
            ->map(fn (array $config, string $key): array => [
                'key' => $key,
                'label' => (string) ($config['label'] ?? Str::headline($key)),
                'type' => (string) ($config['type'] ?? 'text_to_speech'),
                'voice_id' => $this->voiceId($key),
                'model' => $this->model($key, 'en'),
                'english_model' => $this->model($key, 'en'),
                'afrikaans_model' => $this->model($key, 'af'),
                'base_url' => $this->baseUrl($key),
                'output_format' => $this->outputFormat($key),
                'configured' => $this->configured($key),
                'source' => $this->apiKeySource($key),
                'masked_key' => $this->maskedApiKey($key),
                'key_optional' => (bool) config("services.voice.providers.{$key}.key_optional", false),
            ])
            ->values()
            ->all();
    }

    public function status(): array
    {
        return [
            'provider' => $this->provider(),
            'provider_label' => $this->providerLabel($this->provider()),
            'voice_id' => $this->voiceId(),
            'model' => $this->model(),
            'english_model' => $this->model(null, 'en'),
            'afrikaans_model' => $this->model(null, 'af'),
            'base_url' => $this->baseUrl(),
            'output_format' => $this->outputFormat(),
            'configured' => $this->configured(),
            'source' => $this->apiKeySource(),
            'masked_key' => $this->maskedApiKey(),
            'providers' => $this->providers(),
        ];
    }

    public function provider(): string
    {
        $provider = (string) (Setting::getValue('voice.provider') ?: config('services.voice.provider', 'elevenlabs'));

        return array_key_exists($provider, (array) config('services.voice.providers', [])) ? $provider : 'elevenlabs';
    }

    public function providerLabel(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (config("services.voice.providers.{$provider}.label") ?: Str::headline($provider));
    }

    public function model(?string $provider = null, ?string $locale = null): string
    {
        $provider ??= $this->provider();
        $locale = $this->normalizeLocale($locale ?: 'en');

        if ($locale === 'af') {
            return (string) (
                Setting::getValue("voice.{$provider}_afrikaans_model")
                ?: config("services.voice.providers.{$provider}.afrikaans_model", '')
            );
        }

        return (string) (
            Setting::getValue("voice.{$provider}_english_model")
            ?: Setting::getValue("voice.{$provider}_model")
            ?: config("services.voice.providers.{$provider}.english_model")
            ?: config("services.voice.providers.{$provider}.model", '')
        );
    }

    public function voiceId(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (
            Setting::getValue("voice.{$provider}_voice_id")
            ?: config("services.voice.providers.{$provider}.voice_id", '')
        );
    }

    public function baseUrl(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return rtrim((string) (
            Setting::getValue("voice.{$provider}_base_url")
            ?: config("services.voice.providers.{$provider}.base_url", '')
        ), '/');
    }

    public function outputFormat(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return (string) (
            Setting::getValue("voice.{$provider}_output_format")
            ?: config("services.voice.providers.{$provider}.output_format", 'mp3_44100_128')
        );
    }

    public function configured(?string $provider = null): bool
    {
        $provider ??= $this->provider();

        $keyOptional = (bool) config("services.voice.providers.{$provider}.key_optional", false);

        return ($keyOptional || $this->apiKey($provider) !== '')
            && $this->voiceId($provider) !== ''
            && $this->model($provider, 'en') !== ''
            && $this->model($provider, 'af') !== ''
            && $this->baseUrl($provider) !== '';
    }

    public function apiKeySource(?string $provider = null): string
    {
        $provider ??= $this->provider();

        if ((string) config("services.voice.providers.{$provider}.key", '') !== '') {
            return 'Environment';
        }

        if ((string) Setting::getValue("voice.{$provider}_api_key", '') !== '') {
            return 'Voice settings';
        }

        if ((bool) config("services.voice.providers.{$provider}.key_optional", false)) {
            return 'Optional local key';
        }

        return 'Missing';
    }

    public function maskedApiKey(?string $provider = null): string
    {
        $key = $this->apiKey($provider ?? $this->provider());

        return $key === '' ? '' : str_repeat('*', max(strlen($key) - 4, 8)).substr($key, -4);
    }

    public function speakAskLife(string $text, ?string $locale = null, ?User $user = null): array
    {
        $text = $this->cleanText($text);
        $locale = $this->normalizeLocale($locale ?: $this->detectLocale($text));

        if ($text === '') {
            return ['ok' => false, 'message' => 'No text was provided for speech.'];
        }

        if (! $this->configured()) {
            return ['ok' => false, 'message' => "{$this->providerLabel()} is not configured for Jimmy's spoken replies."];
        }

        $provider = $this->provider();
        $model = $this->model($provider, $locale);
        $voiceId = $this->voiceId($provider);
        $outputFormat = $this->outputFormat($provider);
        $hash = hash('sha256', $provider.'|'.$voiceId.'|'.$model.'|'.$locale.'|'.$outputFormat.'|'.$text);
        $path = 'ask-life/voice/'.$hash.'.'.$this->extensionForFormat($outputFormat);

        if (Storage::disk('public')->exists($path)) {
            return [
                'ok' => true,
                'message' => 'Spoken Jimmy reply loaded from cache.',
                'audio_url' => Storage::disk('public')->url($path),
                'locale' => $locale,
                'cached' => true,
                'mime_type' => $this->mimeTypeForFormat($outputFormat),
            ];
        }

        $input = [
            'text' => $text,
            'locale' => $locale,
            'provider' => $provider,
            'model' => $model,
            'voice_id' => $voiceId,
            'output_format' => $outputFormat,
        ];

        $generation = AiGeneration::create([
            'feature_key' => 'ask_life_voice',
            'user_id' => $user?->id,
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => 'ask_life_voice_v1',
            'input_hash' => hash('sha256', $this->encode($input)),
            'input_summary' => Str::limit($text, 1200, ''),
            'input_payload' => $input,
            'output_language' => $locale,
            'status' => AiGeneration::STATUS_DRAFT,
            'token_input_estimate' => mb_strlen($text),
            'cost_estimate' => 0,
        ]);

        if ($message = $this->budget->blockReason('ask_life_voice')) {
            $generation->update([
                'status' => AiGeneration::STATUS_FAILED,
                'error_message' => $message,
            ]);

            return [
                'ok' => false,
                'message' => $message,
                'generation_id' => $generation->id,
            ];
        }

        try {
            $audio = $this->generateAudio($provider, $text, $locale, $model, $voiceId, $outputFormat);
            Storage::disk('public')->put($path, $audio['bytes']);

            $generation->update([
                'status' => AiGeneration::STATUS_ACCEPTED,
                'output_payload' => [
                    'path' => $path,
                    'audio_url' => Storage::disk('public')->url($path),
                    'locale' => $locale,
                    'voice_id' => $voiceId,
                    'output_format' => $outputFormat,
                    'mime_type' => $audio['mime_type'],
                    'character_count' => mb_strlen($text),
                    'cached' => false,
                ],
                'token_output_estimate' => mb_strlen($text),
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
                'cost_estimate' => $this->costs->estimateVoice($provider, $model, mb_strlen($text)),
            ]);

            return [
                'ok' => true,
                'message' => 'Spoken Jimmy reply generated.',
                'audio_url' => Storage::disk('public')->url($path),
                'locale' => $locale,
                'cached' => false,
                'mime_type' => $audio['mime_type'],
                'generation_id' => $generation->id,
            ];
        } catch (Throwable $exception) {
            Log::warning('Voice generation failed.', [
                'feature' => 'ask_life_voice',
                'provider' => $provider,
                'model' => $model,
                'message' => $exception->getMessage(),
            ]);

            $generation->update([
                'status' => AiGeneration::STATUS_FAILED,
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
            ]);

            return [
                'ok' => false,
                'message' => $exception->getMessage(),
                'generation_id' => $generation->id,
            ];
        }
    }

    public function detectLocale(string $text): string
    {
        $normalized = ' '.mb_strtolower($text).' ';

        $afrikaansMarkers = [
            ' asseblief ', ' dankie ', ' waar ', ' wanneer ', ' hoekom ', ' hoeveel ', ' naby ',
            ' besigheid ', ' geleentheid ', ' advertensie ', ' fout ', ' waterlek ', ' krag ',
            ' pad ', ' slaggat ', ' vandag ', ' hierdie ', ' soek ', ' help my ', ' is daar ',
            ' in bethlehem ', ' in harrismith ',
        ];

        foreach ($afrikaansMarkers as $marker) {
            if (str_contains($normalized, $marker)) {
                return 'af';
            }
        }

        return 'en';
    }

    private function generateWithElevenLabs(string $text, string $locale, string $model, string $voiceId, string $outputFormat): array
    {
        $query = http_build_query(['output_format' => $outputFormat]);

        $response = Http::withHeaders(['xi-api-key' => $this->apiKey('elevenlabs')])
            ->accept($this->mimeTypeForFormat($outputFormat))
            ->asJson()
            ->timeout($this->timeout())
            ->post($this->baseUrl('elevenlabs').'/text-to-speech/'.$voiceId.'?'.$query, [
                'text' => $text,
                'model_id' => $model,
                'language_code' => $locale,
                'voice_settings' => [
                    'stability' => 0.45,
                    'similarity_boost' => 0.75,
                ],
            ]);

        $this->throwIfFailed($response, 'elevenlabs');

        $bytes = $response->body();
        if ($bytes === '') {
            throw new RuntimeException('ElevenLabs returned an empty audio file.');
        }

        return [
            'bytes' => $bytes,
            'mime_type' => (string) ($response->header('Content-Type') ?: $this->mimeTypeForFormat($outputFormat)),
        ];
    }

    private function generateAudio(string $provider, string $text, string $locale, string $model, string $voiceId, string $outputFormat): array
    {
        $type = (string) config("services.voice.providers.{$provider}.type", 'text_to_speech');

        return match ($type) {
            'nvidia_speech_nim' => $this->generateWithNvidiaSpeechNim($provider, $text, $locale, $model, $voiceId, $outputFormat),
            default => $this->generateWithElevenLabs($text, $locale, $model, $voiceId, $outputFormat),
        };
    }

    private function generateWithNvidiaSpeechNim(string $provider, string $text, string $locale, string $model, string $voiceId, string $outputFormat): array
    {
        $language = $this->nvidiaLanguage($model, $locale);
        $request = Http::accept($this->mimeTypeForFormat($outputFormat))
            ->asMultipart()
            ->timeout($this->timeout());

        if ($this->apiKey($provider) !== '') {
            $request = $request->withToken($this->apiKey($provider));
        }

        $response = $request->post($this->nvidiaSpeechEndpoint($provider), [
            'language' => $language,
            'text' => $text,
            'voice' => $voiceId,
            'sample_rate_hz' => $this->sampleRateForFormat($outputFormat),
        ]);

        $this->throwIfFailed($response, $provider);

        return $this->audioFromResponse($response, $this->mimeTypeForFormat($outputFormat));
    }

    private function throwIfFailed(Response $response, ?string $provider = null): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('detail.message')
            ?: $response->json('message')
            ?: $response->json('error.message')
            ?: $response->json('detail')
            ?: $response->body();

        $label = $provider ? $this->providerLabel($provider) : 'Voice provider';

        throw new RuntimeException($label.' returned '.$response->status().': '.Str::limit(trim((string) $message), 300));
    }

    private function apiKey(string $provider): string
    {
        $value = (string) (
            config("services.voice.providers.{$provider}.key")
            ?: Setting::getValue("voice.{$provider}_api_key", '')
        );

        $value = trim($value, " \t\n\r\0\x0B\"'");

        return Str::startsWith($value, 'Bearer ') ? trim(substr($value, 7)) : $value;
    }

    private function cleanText(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        return Str::limit($text, 1000, '');
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = mb_strtolower(trim($locale));

        return in_array($locale, ['af', 'afr', 'afrikaans'], true) ? 'af' : 'en';
    }

    private function extensionForFormat(string $outputFormat): string
    {
        return match (true) {
            str_starts_with($outputFormat, 'wav') => 'wav',
            str_starts_with($outputFormat, 'pcm') => 'pcm',
            str_starts_with($outputFormat, 'ulaw') => 'ulaw',
            str_starts_with($outputFormat, 'alaw') => 'alaw',
            str_starts_with($outputFormat, 'opus') => 'opus',
            default => 'mp3',
        };
    }

    private function mimeTypeForFormat(string $outputFormat): string
    {
        return match (true) {
            str_starts_with($outputFormat, 'wav') => 'audio/wav',
            str_starts_with($outputFormat, 'pcm') => 'audio/L16',
            str_starts_with($outputFormat, 'ulaw') => 'audio/basic',
            str_starts_with($outputFormat, 'alaw') => 'audio/basic',
            str_starts_with($outputFormat, 'opus') => 'audio/ogg',
            default => 'audio/mpeg',
        };
    }

    private function nvidiaSpeechEndpoint(string $provider): string
    {
        $baseUrl = $this->baseUrl($provider);

        if (Str::endsWith($baseUrl, '/audio/synthesize')) {
            return $baseUrl;
        }

        return $baseUrl.'/audio/synthesize';
    }

    private function nvidiaLanguage(string $model, string $locale): string
    {
        $model = trim($model);
        if ($model !== '') {
            return $model;
        }

        return $locale === 'af' ? 'en-US' : 'en-US';
    }

    private function sampleRateForFormat(string $outputFormat): int
    {
        if (preg_match('/_(\d{4,6})/', $outputFormat, $matches)) {
            return max(8000, (int) $matches[1]);
        }

        return 22050;
    }

    private function audioFromResponse(Response $response, string $fallbackMimeType): array
    {
        $mimeType = (string) ($response->header('Content-Type') ?: $fallbackMimeType);
        $body = $response->body();

        if (str_contains(Str::lower($mimeType), 'json') || Str::startsWith(trim($body), '{')) {
            $payload = $response->json();
            foreach (['audio', 'audio_content', 'audioContent', 'data.audio', 'data.0.audio', 'data.0.b64_json'] as $path) {
                $audio = data_get($payload, $path);

                if (is_string($audio) && trim($audio) !== '') {
                    return [
                        'bytes' => $this->decodeBase64Audio($audio),
                        'mime_type' => $fallbackMimeType,
                    ];
                }
            }
        }

        if ($body === '') {
            throw new RuntimeException('Voice provider returned an empty audio file.');
        }

        return ['bytes' => $body, 'mime_type' => $mimeType];
    }

    private function decodeBase64Audio(string $base64): string
    {
        if (str_contains($base64, ',')) {
            $base64 = (string) Str::after($base64, ',');
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Voice provider returned invalid base64 audio data.');
        }

        return $bytes;
    }

    private function timeout(): int
    {
        return max(15, (int) config('services.voice.timeout', 60));
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
