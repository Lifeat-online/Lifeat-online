<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When false, OperatorPushNotifier::send() is a no-op. Useful for local
    | development and the CI test environment. Production must set
    | OPS_ALERTS_ENABLED=true.
    |
    */
    'enabled' => (bool) env('OPS_ALERTS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Operator roster (always-on)
    |--------------------------------------------------------------------------
    |
    | Comma-separated user IDs that always receive every alert target, in
    | addition to the role-based resolution below. Used for the on-call
    | rotation.
    |
    */
    'explicit_user_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('OPS_ALERT_USER_IDS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Dev is admin
    |--------------------------------------------------------------------------
    |
    | On this platform dev/developer is treated as an admin role and receives
    | every business push. Toggle off to opt out.
    |
    */
    'dev_is_admin' => (bool) env('OPS_DEV_IS_ADMIN', true),

    /*
    |--------------------------------------------------------------------------
    | Alert targets
    |--------------------------------------------------------------------------
    |
    | Each target has:
    |   severity      - critical | warning | info
    |   category      - operational | business
    |   roles         - additional roles (besides dev + admin + support) that
    |                   receive this target
    |
    | Category = operational targets are sent to dev + admin + support.
    | Category = business targets are sent to dev + admin (NOT support).
    |
    */
    'targets' => [
        'backup:failed' => ['severity' => 'critical', 'category' => 'operational'],
        'monitoring:degraded' => ['severity' => 'critical', 'category' => 'operational'],
        'production:check:err' => ['severity' => 'critical', 'category' => 'operational'],
        'disk:warning' => ['severity' => 'warning', 'category' => 'operational'],
        'disk:critical' => ['severity' => 'critical', 'category' => 'operational'],
        'queue:backlog' => ['severity' => 'warning', 'category' => 'operational'],
        'deploy:failed' => ['severity' => 'critical', 'category' => 'operational'],
        'backup:prune:large' => ['severity' => 'info', 'category' => 'operational'],

        'user:registered' => ['severity' => 'info', 'category' => 'business'],
        'user:staff_registered' => ['severity' => 'info', 'category' => 'business'],
        'writer:applied' => ['severity' => 'info', 'category' => 'business'],
        'mall:vendor_applied' => ['severity' => 'info', 'category' => 'business'],
        'finance:payout_requested' => ['severity' => 'info', 'category' => 'business'],
        'finance:refund' => ['severity' => 'warning', 'category' => 'business'],
        'finance:payout_paid' => ['severity' => 'info', 'category' => 'business'],
        'classified:pending' => ['severity' => 'info', 'category' => 'business'],
        'review:pending' => ['severity' => 'info', 'category' => 'business'],
        'action:movement' => ['severity' => 'warning', 'category' => 'business'],
        'action:escalated' => ['severity' => 'critical', 'category' => 'business'],
        'transport:incident' => ['severity' => 'warning', 'category' => 'business'],
        'civic:fault' => ['severity' => 'info', 'category' => 'business'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Re-send policy
    |--------------------------------------------------------------------------
    |
    | critical alerts that are not acknowledged within ack_window_minutes are
    | re-sent every retry_after_minutes, capped at max_retries times.
    |
    */
    'ack_window_minutes' => (int) env('OPS_ACK_WINDOW_MINUTES', 30),
    'retry_after_minutes' => (int) env('OPS_RETRY_AFTER_MINUTES', 15),
    'max_retries' => (int) env('OPS_MAX_RETRIES', 4),

    /*
    |--------------------------------------------------------------------------
    | Disk alert thresholds
    |--------------------------------------------------------------------------
    */
    'disk' => [
        'warning_percent' => (int) env('OPS_DISK_WARNING_PERCENT', 80),
        'critical_percent' => (int) env('OPS_DISK_CRITICAL_PERCENT', 95),
        'path' => env('OPS_DISK_PATH', '/app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue backlog threshold
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('OPS_QUEUE_CONNECTION', 'database'),
        'queue_name' => env('OPS_QUEUE_NAME', 'default'),
        'depth_warning' => (int) env('OPS_QUEUE_DEPTH_WARNING', 500),
        'depth_critical' => (int) env('OPS_QUEUE_DEPTH_CRITICAL', 2000),
    ],
];
