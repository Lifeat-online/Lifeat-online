<?php

return [
    'currency' => env('AI_COST_CURRENCY', 'ZAR'),
    'rates_currency' => 'USD',
    'usd_to_zar' => env('AI_COST_USD_TO_ZAR', 16.46),
    'exchange_rate_note' => 'Planning default only. Update AI_COST_USD_TO_ZAR when you want current rand estimates.',

    'budget' => [
        'monthly_limit_zar' => env('AI_MONTHLY_BUDGET_ZAR', 0),
        'warning_percent' => env('AI_BUDGET_WARNING_PERCENT', 80),
        'hard_stop_enabled' => env('AI_BUDGET_HARD_STOP_ENABLED', false),
        'exempt_features' => [
            'settings_test',
        ],
    ],

    'text' => [
        'openrouter' => [
            'default' => [
                'input_per_million' => env('AI_COST_OPENROUTER_INPUT_PER_MILLION', 0),
                'output_per_million' => env('AI_COST_OPENROUTER_OUTPUT_PER_MILLION', 0),
            ],
            'contains' => [
                ':free' => ['input_per_million' => 0, 'output_per_million' => 0],
            ],
        ],
        'google' => [
            'default' => [
                'input_per_million' => env('AI_COST_GOOGLE_INPUT_PER_MILLION', 0.15),
                'output_per_million' => env('AI_COST_GOOGLE_OUTPUT_PER_MILLION', 0.60),
            ],
        ],
        'anthropic' => [
            'default' => [
                'input_per_million' => env('AI_COST_ANTHROPIC_INPUT_PER_MILLION', 3.00),
                'output_per_million' => env('AI_COST_ANTHROPIC_OUTPUT_PER_MILLION', 15.00),
            ],
        ],
        'openai' => [
            'default' => [
                'input_per_million' => env('AI_COST_OPENAI_INPUT_PER_MILLION', 0.15),
                'output_per_million' => env('AI_COST_OPENAI_OUTPUT_PER_MILLION', 0.60),
            ],
        ],
    ],

    'image' => [
        'openrouter' => [
            'default_per_image' => env('AI_COST_OPENROUTER_IMAGE_PER_IMAGE', 0),
            'contains' => [
                ':free' => 0,
            ],
        ],
        'openai' => [
            'default_per_image' => env('AI_COST_OPENAI_IMAGE_PER_IMAGE', 0.04),
        ],
        'google' => [
            'default_per_image' => env('AI_COST_GOOGLE_IMAGE_PER_IMAGE', 0.04),
        ],
        'nvidia' => [
            'default_per_image' => env('AI_COST_NVIDIA_IMAGE_PER_IMAGE', 0.04),
        ],
    ],

    'voice' => [
        'elevenlabs' => [
            'default_per_1000_characters' => env('AI_COST_ELEVENLABS_PER_1000_CHARACTERS', 0.06),
        ],
        'nvidia' => [
            'default_per_1000_characters' => env('AI_COST_NVIDIA_VOICE_PER_1000_CHARACTERS', 0),
        ],
    ],
];
