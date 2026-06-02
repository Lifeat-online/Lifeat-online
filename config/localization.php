<?php

return [
    'default' => env('APP_LOCALE', 'en'),

    'auto_translate_on_publish' => env('AUTO_TRANSLATE_ON_PUBLISH', env('APP_ENV') !== 'testing'),

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
