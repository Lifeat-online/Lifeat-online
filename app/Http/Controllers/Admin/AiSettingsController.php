<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AiGatewayService;
use App\Services\AiImageService;
use App\Services\VoiceGatewayService;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiSettingsController extends Controller
{
    public function save(Request $request, AiGatewayService $gateway, AiImageService $images, VoiceGatewayService $voice): JsonResponse|RedirectResponse
    {
        $this->ensureDevOwner($request);

        $providers = collect($gateway->providers())->pluck('key')->all();
        $imageProviders = collect($images->providers())->pluck('key')->all();
        $voiceProviders = collect($voice->providers())->pluck('key')->all();

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($providers)],
            'image_provider' => ['nullable', 'string', Rule::in($imageProviders)],
            'voice_provider' => ['nullable', 'string', Rule::in($voiceProviders)],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['nullable', 'string', 'max:1000'],
            'models' => ['nullable', 'array'],
            'models.*' => ['nullable', 'string', 'max:255'],
            'base_urls' => ['nullable', 'array'],
            'base_urls.*' => ['nullable', 'string', 'max:500'],
            'image_keys' => ['nullable', 'array'],
            'image_keys.*' => ['nullable', 'string', 'max:1000'],
            'image_models' => ['nullable', 'array'],
            'image_models.*' => ['nullable', 'string', 'max:255'],
            'image_base_urls' => ['nullable', 'array'],
            'image_base_urls.*' => ['nullable', 'string', 'max:500'],
            'image_sizes' => ['nullable', 'array'],
            'image_sizes.*' => ['nullable', 'string', 'max:80'],
            'voice_key' => ['nullable', 'string', 'max:1000'],
            'voice_voice_id' => ['nullable', 'string', 'max:255'],
            'voice_english_model' => ['nullable', 'string', 'max:255'],
            'voice_afrikaans_model' => ['nullable', 'string', 'max:255'],
            'voice_base_url' => ['nullable', 'string', 'max:500'],
            'voice_output_format' => ['nullable', 'string', 'max:80'],
            'voice_keys' => ['nullable', 'array'],
            'voice_keys.*' => ['nullable', 'string', 'max:1000'],
            'voice_voice_ids' => ['nullable', 'array'],
            'voice_voice_ids.*' => ['nullable', 'string', 'max:255'],
            'voice_english_models' => ['nullable', 'array'],
            'voice_english_models.*' => ['nullable', 'string', 'max:255'],
            'voice_afrikaans_models' => ['nullable', 'array'],
            'voice_afrikaans_models.*' => ['nullable', 'string', 'max:255'],
            'voice_base_urls' => ['nullable', 'array'],
            'voice_base_urls.*' => ['nullable', 'string', 'max:500'],
            'voice_output_formats' => ['nullable', 'array'],
            'voice_output_formats.*' => ['nullable', 'string', 'max:80'],
            'azure_openai_api_version' => ['nullable', 'string', 'max:80'],
        ]);

        $this->setSetting($request, 'ai.provider', $validated['provider'], 'string');
        if (filled($validated['image_provider'] ?? null)) {
            $this->setSetting($request, 'ai_image.provider', $validated['image_provider'], 'string');
        }
        if (filled($validated['voice_provider'] ?? null)) {
            $this->setSetting($request, 'voice.provider', $validated['voice_provider'], 'string');
        }

        foreach ($providers as $provider) {
            $key = trim((string) data_get($validated, "keys.{$provider}", ''));
            $model = trim((string) data_get($validated, "models.{$provider}", ''));
            $baseUrl = trim((string) data_get($validated, "base_urls.{$provider}", ''));

            if ($key !== '') {
                $this->setSetting($request, "ai.{$provider}_api_key", $key, 'secret');
            }

            if ($model !== '') {
                $this->setSetting($request, "ai.{$provider}_model", $model, 'string');
            }

            if ($baseUrl !== '') {
                $this->setSetting($request, "ai.{$provider}_base_url", rtrim($baseUrl, '/'), 'string');
            }
        }

        foreach ($imageProviders as $provider) {
            $key = trim((string) data_get($validated, "image_keys.{$provider}", ''));
            $model = trim((string) data_get($validated, "image_models.{$provider}", ''));
            $baseUrl = trim((string) data_get($validated, "image_base_urls.{$provider}", ''));
            $size = trim((string) data_get($validated, "image_sizes.{$provider}", ''));

            if ($key !== '') {
                $this->setSetting($request, "ai_image.{$provider}_api_key", $key, 'secret');
            }

            if ($model !== '') {
                $this->setSetting($request, "ai_image.{$provider}_model", $model, 'string');
            }

            if ($baseUrl !== '') {
                $this->setSetting($request, "ai_image.{$provider}_base_url", rtrim($baseUrl, '/'), 'string');
            }

            if ($size !== '') {
                $this->setSetting($request, "ai_image.{$provider}_size", $size, 'string');
            }
        }

        if (filled($validated['azure_openai_api_version'] ?? null)) {
            $this->setSetting($request, 'ai.azure_openai_api_version', trim($validated['azure_openai_api_version']), 'string');
        }

        foreach ($voiceProviders as $provider) {
            $key = trim((string) data_get($validated, "voice_keys.{$provider}", ''));
            $voiceId = trim((string) data_get($validated, "voice_voice_ids.{$provider}", ''));
            $englishModel = trim((string) data_get($validated, "voice_english_models.{$provider}", ''));
            $afrikaansModel = trim((string) data_get($validated, "voice_afrikaans_models.{$provider}", ''));
            $baseUrl = trim((string) data_get($validated, "voice_base_urls.{$provider}", ''));
            $outputFormat = trim((string) data_get($validated, "voice_output_formats.{$provider}", ''));

            if ($key !== '') {
                $this->setSetting($request, "voice.{$provider}_api_key", $key, 'secret');
            }

            if ($voiceId !== '') {
                $this->setSetting($request, "voice.{$provider}_voice_id", $voiceId, 'string');
            }

            if ($englishModel !== '') {
                $this->setSetting($request, "voice.{$provider}_english_model", $englishModel, 'string');
            }

            if ($afrikaansModel !== '') {
                $this->setSetting($request, "voice.{$provider}_afrikaans_model", $afrikaansModel, 'string');
            }

            if ($baseUrl !== '') {
                $this->setSetting($request, "voice.{$provider}_base_url", rtrim($baseUrl, '/'), 'string');
            }

            if ($outputFormat !== '') {
                $this->setSetting($request, "voice.{$provider}_output_format", $outputFormat, 'string');
            }
        }

        $voiceProvider = filled($validated['voice_provider'] ?? null) ? $validated['voice_provider'] : $voice->provider();
        $voiceKey = trim((string) ($validated['voice_key'] ?? ''));
        $voiceVoiceId = trim((string) ($validated['voice_voice_id'] ?? ''));
        $voiceEnglishModel = trim((string) ($validated['voice_english_model'] ?? ''));
        $voiceAfrikaansModel = trim((string) ($validated['voice_afrikaans_model'] ?? ''));
        $voiceBaseUrl = trim((string) ($validated['voice_base_url'] ?? ''));
        $voiceOutputFormat = trim((string) ($validated['voice_output_format'] ?? ''));

        if ($voiceKey !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_api_key", $voiceKey, 'secret');
        }

        if ($voiceVoiceId !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_voice_id", $voiceVoiceId, 'string');
        }

        if ($voiceEnglishModel !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_english_model", $voiceEnglishModel, 'string');
        }

        if ($voiceAfrikaansModel !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_afrikaans_model", $voiceAfrikaansModel, 'string');
        }

        if ($voiceBaseUrl !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_base_url", rtrim($voiceBaseUrl, '/'), 'string');
        }

        if ($voiceOutputFormat !== '') {
            $this->setSetting($request, "voice.{$voiceProvider}_output_format", $voiceOutputFormat, 'string');
        }

        $payload = [
            'ok' => true,
            'message' => 'AI provider settings saved.',
            'status' => $gateway->status(),
            'image_status' => $images->status(),
            'voice_status' => $voice->status(),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('status', $payload['message']);
    }

    public function test(Request $request, AiGatewayService $gateway, AiPromptCatalog $prompts): JsonResponse|RedirectResponse
    {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'prompt' => ['nullable', 'string', 'max:1000'],
        ]);

        $prompt = $prompts->get('settings_test');
        $result = $gateway->generateStructured(
            'settings_test',
            $prompt['version'],
            $prompt['system'],
            [
                'prompt' => trim((string) ($validated['prompt'] ?? 'Say that the Life@ AI provider is ready.')),
                'schema' => $prompt['schema'],
            ],
            null,
            $request->user(),
            'en',
        );

        if ($request->expectsJson()) {
            return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
        }

        $message = ($result['ok'] ?? false)
            ? 'AI test passed: '.(string) data_get($result, 'payload.summary', 'Provider returned a valid response.')
            : 'AI test failed: '.($result['message'] ?? 'Provider unavailable.');

        return back()->with('status', $message);
    }

    private function setSetting(Request $request, string $key, string $value, string $type): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => 'ai',
                'updated_by_user_id' => $request->user()?->id,
            ]
        );
    }

    private function ensureDevOwner(Request $request): void
    {
        if (strtolower((string) $request->user()?->email) !== 'jameskoen78@gmail.com') {
            abort(403);
        }
    }
}
