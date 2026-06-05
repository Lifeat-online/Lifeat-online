<?php

$ignoreStatuses = array_values(array_filter(array_map(
    static fn (string $status): int => (int) trim($status),
    explode(',', (string) env('ERROR_TRACKING_IGNORE_STATUSES', '404,419'))
)));

return [
    'enabled' => (bool) env('ERROR_TRACKING_ENABLED', false),
    'driver' => env('ERROR_TRACKING_DRIVER', 'log'),
    'provider' => env('ERROR_TRACKING_PROVIDER', 'custom'),
    'webhook_url' => env('ERROR_TRACKING_WEBHOOK_URL'),
    'timeout' => (int) env('ERROR_TRACKING_TIMEOUT', 3),
    'sample_rate' => (float) env('ERROR_TRACKING_SAMPLE_RATE', 1.0),
    'ignore_statuses' => $ignoreStatuses,
    'include_trace' => (bool) env('ERROR_TRACKING_INCLUDE_TRACE', true),
    'trace_frames' => (int) env('ERROR_TRACKING_TRACE_FRAMES', 12),
    'log_channel' => env('ERROR_TRACKING_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    'environment' => env('ERROR_TRACKING_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('ERROR_TRACKING_RELEASE', env('APP_VERSION')),
];
