<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Life@ Read Cache TTLs
    |--------------------------------------------------------------------------
    |
    | These caches are intentionally scoped to stable public read data: settings,
    | package catalogues, category/reference lists, and lightweight discovery
    | counters. Set a TTL to 0 to bypass a cache group during local debugging.
    |
    */

    'settings_ttl' => (int) env('LIFEAT_SETTINGS_CACHE_TTL', 3600),
    'catalog_ttl' => (int) env('LIFEAT_CATALOG_CACHE_TTL', 1800),
    'public_ttl' => (int) env('LIFEAT_PUBLIC_CACHE_TTL', 300),
];
