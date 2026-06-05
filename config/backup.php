<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup root directory
    |--------------------------------------------------------------------------
    |
    | Local directory where backup archives are staged before (optionally)
    | being uploaded to S3-compatible storage. Each backup type lives in
    | its own subdirectory: <root>/db, <root>/storage, <root>/logs.
    |
    */
    'local_path' => env('BACKUP_LOCAL_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Retention window
    |--------------------------------------------------------------------------
    |
    | Number of days to keep backups before they are pruned. Applies to both
    | local archives and (when S3 is enabled) remote copies.
    |
    */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    */
    'db' => [
        'enabled' => env('BACKUP_DB_ENABLED', true),

        // Cron expression – 'daily' is safe for Hetzner VPS volumes < 50 GB.
        'schedule' => env('BACKUP_DB_SCHEDULE', '0 2 * * *'),

        // Optional mysqldump binary path (defaults to system PATH).
        'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BIN', 'mysqldump'),

        // When true, GZIP-compress the dump before upload.
        'compress' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage (media) backups
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'enabled' => env('BACKUP_STORAGE_ENABLED', true),

        // Cron expression – weekly is enough for media; daily is OK too.
        'schedule' => env('BACKUP_STORAGE_SCHEDULE', '0 3 * * 0'),

        // Path inside the project that contains user-uploaded media.
        'source_path' => env('BACKUP_STORAGE_SOURCE', storage_path('app/public')),
    ],

    /*
    |--------------------------------------------------------------------------
    | S3-compatible remote upload (Hetzner Storage Box, B2, AWS, MinIO, …)
    |--------------------------------------------------------------------------
    |
    | Remote upload is OPTIONAL. When BACKUP_S3_BUCKET is empty, the backup
    | scripts will keep archives on the local disk only and skip the upload
    | step entirely. Set the values below to enable off-site backup.
    |
    | Hetzner Storage Box exposes an S3-compatible endpoint with path-style
    | URLs, e.g. https://<username>.your-storagebox.de
    |
    */
    's3' => [
        'enabled' => env('BACKUP_S3_ENABLED', false),
        'bucket' => env('BACKUP_S3_BUCKET'),
        'prefix' => env('BACKUP_S3_PREFIX', 'lifeat'),
        'region' => env('BACKUP_S3_REGION', 'us-east-1'),
        'endpoint' => env('BACKUP_S3_ENDPOINT'),
        'use_path_style' => (bool) env('BACKUP_S3_PATH_STYLE', true),
        'access_key' => env('BACKUP_S3_ACCESS_KEY'),
        'secret_key' => env('BACKUP_S3_SECRET_KEY'),

        // Optional – skip upload when the local archive is bigger than this.
        'max_upload_bytes' => (int) env('BACKUP_S3_MAX_BYTES', 5 * 1024 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification webhook
    |--------------------------------------------------------------------------
    |
    | Healthchecks.io (or any URL that returns 2xx on success) will be pinged
    | after every backup run. Failures send /fail; successes send /<uuid>.
    |
    */
    'healthcheck_url' => env('BACKUP_HEALTHCHECK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    */
    'prune_schedule' => env('BACKUP_PRUNE_SCHEDULE', '15 4 * * *'),
];
