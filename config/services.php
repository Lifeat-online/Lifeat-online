<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_TRANSLATION_MODEL', 'google/gemma-4-31b-it:free'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout' => env('OPENROUTER_TIMEOUT', 90),
        'max_tokens' => env('OPENROUTER_TRANSLATION_MAX_TOKENS', 4096),
        'structured_outputs' => env('OPENROUTER_STRUCTURED_OUTPUTS', true),
    ],

    'azure_translator' => [
        'key' => env('AZURE_TRANSLATOR_KEY'),
        'region' => env('AZURE_TRANSLATOR_REGION'),
        'endpoint' => env('AZURE_TRANSLATOR_ENDPOINT', 'https://api.cognitive.microsofttranslator.com'),
        'timeout' => env('AZURE_TRANSLATOR_TIMEOUT', 30),
    ],

    'google_translate' => [
        'key' => env('GOOGLE_TRANSLATE_API_KEY'),
        'endpoint' => env('GOOGLE_TRANSLATE_ENDPOINT', 'https://translation.googleapis.com/language/translate/v2'),
        'timeout' => env('GOOGLE_TRANSLATE_TIMEOUT', 30),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'places_endpoint' => env('GOOGLE_PLACES_ENDPOINT', 'https://places.googleapis.com/v1'),
        'geocoding_endpoint' => env('GOOGLE_GEOCODING_ENDPOINT', 'https://maps.googleapis.com/maps/api/geocode/json'),
        'timeout' => env('GOOGLE_MAPS_TIMEOUT', 15),
    ],

    'translation' => [
        'provider' => env('TRANSLATION_PROVIDER', 'google'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openrouter'),
        'timeout' => env('AI_TIMEOUT', 90),
        'max_tokens' => env('AI_MAX_TOKENS', 2048),
        'temperature' => env('AI_TEMPERATURE', 0.2),
        'providers' => [
            'openrouter' => [
                'label' => 'OpenRouter',
                'key' => env('AI_OPENROUTER_API_KEY', env('OPENROUTER_API_KEY')),
                'model' => env('AI_OPENROUTER_MODEL', env('OPENROUTER_TRANSLATION_MODEL', 'google/gemma-4-31b-it:free')),
                'base_url' => env('AI_OPENROUTER_BASE_URL', env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1')),
                'type' => 'openai_compatible',
            ],
            'openai' => [
                'label' => 'OpenAI',
                'key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_AI_MODEL', 'gpt-4o-mini'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'type' => 'openai_compatible',
            ],
            'anthropic' => [
                'label' => 'Anthropic',
                'key' => env('ANTHROPIC_API_KEY'),
                'model' => env('ANTHROPIC_AI_MODEL', 'claude-sonnet-4-20250514'),
                'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
                'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
                'type' => 'anthropic',
            ],
            'google' => [
                'label' => 'Google Gemini',
                'key' => env('GEMINI_API_KEY', env('GOOGLE_AI_API_KEY')),
                'model' => env('GEMINI_AI_MODEL', 'gemini-3.5-flash'),
                'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
                'type' => 'gemini',
            ],
            'mistral' => [
                'label' => 'Mistral',
                'key' => env('MISTRAL_API_KEY'),
                'model' => env('MISTRAL_AI_MODEL', 'mistral-large-latest'),
                'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
                'type' => 'openai_compatible',
            ],
            'deepseek' => [
                'label' => 'DeepSeek',
                'key' => env('DEEPSEEK_API_KEY'),
                'model' => env('DEEPSEEK_AI_MODEL', 'deepseek-chat'),
                'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
                'type' => 'openai_compatible',
            ],
            'groq' => [
                'label' => 'Groq',
                'key' => env('GROQ_API_KEY'),
                'model' => env('GROQ_AI_MODEL', 'openai/gpt-oss-20b'),
                'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
                'type' => 'openai_compatible',
            ],
            'xai' => [
                'label' => 'xAI',
                'key' => env('XAI_API_KEY'),
                'model' => env('XAI_AI_MODEL', 'grok-4.3'),
                'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
                'type' => 'openai_compatible',
            ],
            'nvidia' => [
                'label' => 'NVIDIA NIM',
                'key' => env('NVIDIA_API_KEY', env('NVIDIA_NIM_API_KEY')),
                'model' => env('NVIDIA_AI_MODEL', 'meta/llama-3.1-70b-instruct'),
                'base_url' => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
                'type' => 'openai_compatible',
            ],
            'perplexity' => [
                'label' => 'Perplexity',
                'key' => env('PERPLEXITY_API_KEY'),
                'model' => env('PERPLEXITY_AI_MODEL', 'sonar-pro'),
                'base_url' => env('PERPLEXITY_BASE_URL', 'https://api.perplexity.ai/v1'),
                'type' => 'openai_compatible',
            ],
            'together' => [
                'label' => 'Together AI',
                'key' => env('TOGETHER_API_KEY'),
                'model' => env('TOGETHER_AI_MODEL', 'meta-llama/Llama-3.3-70B-Instruct-Turbo'),
                'base_url' => env('TOGETHER_BASE_URL', 'https://api.together.ai/v1'),
                'type' => 'openai_compatible',
            ],
            'fireworks' => [
                'label' => 'Fireworks AI',
                'key' => env('FIREWORKS_API_KEY'),
                'model' => env('FIREWORKS_AI_MODEL', 'accounts/fireworks/models/llama-v3p1-70b-instruct'),
                'base_url' => env('FIREWORKS_BASE_URL', 'https://api.fireworks.ai/inference/v1'),
                'type' => 'openai_compatible',
            ],
            'huggingface' => [
                'label' => 'Hugging Face',
                'key' => env('HUGGINGFACE_API_KEY', env('HF_TOKEN')),
                'model' => env('HUGGINGFACE_AI_MODEL', 'meta-llama/Llama-3.1-8B-Instruct'),
                'base_url' => env('HUGGINGFACE_BASE_URL', 'https://router.huggingface.co/v1'),
                'type' => 'openai_compatible',
            ],
            'azure_openai' => [
                'label' => 'Azure OpenAI',
                'key' => env('AZURE_OPENAI_API_KEY'),
                'model' => env('AZURE_OPENAI_DEPLOYMENT', env('AZURE_OPENAI_AI_MODEL', '')),
                'base_url' => env('AZURE_OPENAI_ENDPOINT', ''),
                'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
                'type' => 'azure_openai',
            ],
            'cohere' => [
                'label' => 'Cohere',
                'key' => env('COHERE_API_KEY'),
                'model' => env('COHERE_AI_MODEL', 'command-a-03-2025'),
                'base_url' => env('COHERE_BASE_URL', 'https://api.cohere.com/v2'),
                'type' => 'cohere',
            ],
            'ollama' => [
                'label' => 'Ollama / Local',
                'key' => env('OLLAMA_API_KEY', ''),
                'model' => env('OLLAMA_AI_MODEL', 'llama3.1'),
                'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
                'type' => 'openai_compatible',
                'key_optional' => true,
            ],
        ],
    ],

    'ai_image' => [
        'provider' => env('AI_IMAGE_PROVIDER', 'openrouter'),
        'timeout' => env('AI_IMAGE_TIMEOUT', 120),
        'fallback_providers' => array_values(array_filter(array_map('trim', explode(',', env('AI_IMAGE_FALLBACK_PROVIDERS', 'openrouter,google,openai'))))),
        'providers' => [
            'openrouter' => [
                'label' => 'OpenRouter Images',
                'key' => env('AI_OPENROUTER_API_KEY', env('OPENROUTER_API_KEY')),
                'model' => env('OPENROUTER_IMAGE_MODEL', 'google/gemini-2.5-flash-image'),
                'base_url' => env('AI_OPENROUTER_BASE_URL', env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1')),
                'size' => env('OPENROUTER_IMAGE_SIZE', '1K'),
                'type' => 'openrouter_chat_image',
            ],
            'openai' => [
                'label' => 'OpenAI Images',
                'key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'size' => env('OPENAI_IMAGE_SIZE', '1024x1024'),
                'type' => 'openai_images',
            ],
            'google' => [
                'label' => 'Google Gemini Images',
                'key' => env('GEMINI_API_KEY', env('GOOGLE_AI_API_KEY')),
                'model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
                'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
                'size' => env('GEMINI_IMAGE_SIZE', '1024x1024'),
                'type' => 'gemini_generate_content',
            ],
            'nvidia' => [
                'label' => 'NVIDIA NIM Images',
                'key' => env('NVIDIA_API_KEY', env('NVIDIA_NIM_API_KEY')),
                'model' => env('NVIDIA_IMAGE_MODEL', 'black-forest-labs/flux.1-dev'),
                'fallback_models' => array_values(array_filter(array_map('trim', explode(',', env('NVIDIA_IMAGE_FALLBACK_MODELS', 'black-forest-labs/flux.1-schnell,stabilityai/stable-diffusion-xl'))))),
                'base_url' => env('NVIDIA_IMAGE_BASE_URL', 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-dev'),
                'size' => env('NVIDIA_IMAGE_SIZE', '1024x1024'),
                'type' => 'nvidia_nim_infer',
            ],
        ],
    ],

    'voice' => [
        'provider' => env('VOICE_PROVIDER', 'elevenlabs'),
        'timeout' => env('VOICE_TIMEOUT', 60),
        'providers' => [
            'elevenlabs' => [
                'label' => 'ElevenLabs',
                'key' => env('ELEVENLABS_API_KEY'),
                'voice_id' => env('ELEVENLABS_VOICE_ID', 'JBFqnCBsd6RMkjVDRZzb'),
                'model' => env('ELEVENLABS_MODEL', 'eleven_flash_v2_5'),
                'english_model' => env('ELEVENLABS_ENGLISH_MODEL', env('ELEVENLABS_MODEL', 'eleven_flash_v2_5')),
                'afrikaans_model' => env('ELEVENLABS_AFRIKAANS_MODEL', 'eleven_v3'),
                'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),
                'output_format' => env('ELEVENLABS_OUTPUT_FORMAT', 'mp3_44100_128'),
                'type' => 'text_to_speech',
            ],
            'nvidia' => [
                'label' => 'NVIDIA Speech NIM',
                'key' => env('NVIDIA_SPEECH_API_KEY', env('NVIDIA_API_KEY', env('NVIDIA_NIM_API_KEY', ''))),
                'voice_id' => env('NVIDIA_TTS_VOICE', 'Magpie-Multilingual.EN-US.Aria'),
                'model' => env('NVIDIA_TTS_MODEL', 'en-US'),
                'english_model' => env('NVIDIA_TTS_ENGLISH_LANGUAGE', env('NVIDIA_TTS_MODEL', 'en-US')),
                'afrikaans_model' => env('NVIDIA_TTS_AFRIKAANS_LANGUAGE', 'en-US'),
                'base_url' => env('NVIDIA_TTS_BASE_URL', 'http://localhost:9000/v1'),
                'output_format' => env('NVIDIA_TTS_OUTPUT_FORMAT', 'wav_22050'),
                'type' => 'nvidia_speech_nim',
                'key_optional' => env('NVIDIA_TTS_KEY_OPTIONAL', true),
            ],
        ],
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'webpush' => [
        'subject' => env('WEBPUSH_VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
    ],

];
