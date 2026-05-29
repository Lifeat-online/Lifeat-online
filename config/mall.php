<?php

return [
    'platform_fee_percent' => env('MALL_PLATFORM_FEE_PERCENT', '10'),
    'currency' => env('MALL_CURRENCY', 'ZAR'),
    'guest_cart_session_key' => 'mall_cart_token',
    'delivery' => [
        'default_area' => 'local',
        'default_method' => 'pickup',
        'methods' => [
            'pickup' => [
                'label' => 'Store pickup',
                'description' => 'Collect from the store or arrange directly with the vendor.',
                'area' => 'all',
                'fee' => '0.00',
                'platform_fee_percent' => '0',
            ],
            'taxi' => [
                'label' => 'Local taxi delivery',
                'description' => 'Uses live local taxi vehicle per-km pricing and sends the delivery into the normal driver offer flow after payment.',
                'area' => 'local',
            ],
            'pudo' => [
                'label' => 'PUDO non-local delivery',
                'description' => 'Live PUDO locker delivery for orders outside the local service area.',
                'area' => 'non_local',
                'platform_fee_percent' => env('MALL_PUDO_DELIVERY_PLATFORM_FEE_PERCENT', '0'),
            ],
        ],
    ],
    'pudo' => [
        'base_url' => env('MALL_PUDO_API_BASE_URL', 'https://api-sandbox.pudo.co.za'),
        'api_key' => env('MALL_PUDO_API_KEY', ''),
        'auth_header' => env('MALL_PUDO_AUTH_HEADER', 'Authorization'),
        'auth_prefix' => env('MALL_PUDO_AUTH_PREFIX', 'Bearer'),
        'timeout' => env('MALL_PUDO_TIMEOUT', 20),
        'provider' => env('MALL_PUDO_PROVIDER', 'tcg-locker'),
        'collection_window_after' => env('MALL_PUDO_COLLECTION_AFTER', '08:00'),
        'collection_window_before' => env('MALL_PUDO_COLLECTION_BEFORE', '16:00'),
        'delivery_window_after' => env('MALL_PUDO_DELIVERY_AFTER', '10:00'),
        'delivery_window_before' => env('MALL_PUDO_DELIVERY_BEFORE', '17:00'),
        'default_parcel' => [
            'length_cm' => env('MALL_PUDO_DEFAULT_LENGTH_CM', 40),
            'width_cm' => env('MALL_PUDO_DEFAULT_WIDTH_CM', 38),
            'height_cm' => env('MALL_PUDO_DEFAULT_HEIGHT_CM', 5),
            'packaging' => env('MALL_PUDO_DEFAULT_PACKAGING', 'Standard flyer'),
        ],
    ],
];
