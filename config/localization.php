<?php

return [
    'default' => env('APP_LOCALE', 'en'),

    'auto_translate_on_publish' => env('AUTO_TRANSLATE_ON_PUBLISH', env('APP_ENV') !== 'testing'),
    'auto_translation_queue' => env('AUTO_TRANSLATION_QUEUE', 'default'),
    'auto_translation_delay_seconds' => (int) env('AUTO_TRANSLATION_DELAY_SECONDS', 0),

    'supported' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'flag' => '🇬🇧',
        ],
        'af' => [
            'name' => 'Afrikaans',
            'native' => 'Afrikaans',
            'flag' => '🇿🇦',
        ],
    ],
];
