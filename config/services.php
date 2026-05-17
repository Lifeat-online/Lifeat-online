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

    'translation' => [
        'provider' => env('TRANSLATION_PROVIDER', 'azure'),
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
