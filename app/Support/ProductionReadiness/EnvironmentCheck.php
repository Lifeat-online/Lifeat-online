<?php

namespace App\Support\ProductionReadiness;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class EnvironmentCheck
{
    /**
     * @return array<int, array{level: string, key: string, message: string}>
     */
    public function run(): array
    {
        $checks = [
            $this->expectEquals('error', 'APP_ENV', (string) config('app.env'), 'production', 'APP_ENV must be production.'),
            $this->expectFalse('error', 'APP_DEBUG', (bool) config('app.debug'), 'APP_DEBUG must be false.'),
            $this->expectPresent('error', 'APP_KEY', (string) config('app.key'), 'APP_KEY must be set.'),
            $this->expectHttpsUrl(),
            $this->expectNotIn('error', 'DB_CONNECTION', (string) config('database.default'), ['sqlite'], 'Production should use a managed database, not SQLite.'),
            $this->expectNotIn('error', 'QUEUE_CONNECTION', (string) config('queue.default'), ['sync'], 'QUEUE_CONNECTION must use an async worker-backed driver.'),
            $this->expectNotIn('warning', 'CACHE_STORE', (string) config('cache.default'), ['array'], 'CACHE_STORE should be persistent in production.'),
            $this->expectNotIn('warning', 'SESSION_DRIVER', (string) config('session.driver'), ['array', 'file'], 'SESSION_DRIVER should be persistent and shared across instances.'),
            $this->expectTrue('warning', 'SESSION_SECURE_COOKIE', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE should be true behind HTTPS.'),
            $this->expectNotIn('warning', 'MAIL_MAILER', (string) config('mail.default'), ['log', 'array'], 'MAIL_MAILER should send real mail in production.'),
            $this->expectRealMailFrom(),
            $this->expectFalse('error', 'DEV_TOOLS_ENABLED', $this->envBool('DEV_TOOLS_ENABLED'), 'DEV_TOOLS_ENABLED must remain false in production.'),
            $this->expectFalse('error', 'DEV_TEST_RUNNER_ENABLED', $this->envBool('DEV_TEST_RUNNER_ENABLED'), 'DEV_TEST_RUNNER_ENABLED must remain false in production.'),
        ];

        return array_values(array_filter(array_merge(
            $checks,
            $this->queueAndSchedulerChecks(),
            $this->uploadStorageChecks(),
            $this->backupChecks(),
            $this->payFastChecks(),
            $this->errorTrackingChecks(),
        )));
    }

    /**
     * @return array<int, array{level: string, key: string, message: string}>
     */
    private function queueAndSchedulerChecks(): array
    {
        $queueWorkerCommand = (string) env('QUEUE_WORKER_COMMAND', '');

        return array_values(array_filter([
            $this->expectTrue('error', 'QUEUE_WORKER_ENABLED', $this->envBool('QUEUE_WORKER_ENABLED'), 'A production queue worker process must be configured and running.'),
            $this->expectPresent('warning', 'QUEUE_WORKER_COMMAND', $queueWorkerCommand, 'Document the production queue worker command, for example php artisan queue:work --sleep=3 --tries=3 --timeout=120.'),
            $this->expectTrue('error', 'SCHEDULER_ENABLED', $this->envBool('SCHEDULER_ENABLED'), 'A production scheduler or cron process must be configured and running.'),
            $this->expectPresent('warning', 'SCHEDULER_COMMAND', (string) env('SCHEDULER_COMMAND', ''), 'Document the production scheduler command, for example php artisan schedule:work or a once-per-minute php artisan schedule:run cron.'),
            ...$this->autoTranslationQueueChecks($queueWorkerCommand),
        ]));
    }

    /**
     * @return array<int, array{level: string, key: string, message: string}>
     */
    private function autoTranslationQueueChecks(string $queueWorkerCommand): array
    {
        if (! (bool) config('localization.auto_translate_on_publish', true)) {
            return [];
        }

        $translationQueue = trim((string) config('localization.auto_translation_queue', 'default'));

        if ($translationQueue === '' || $translationQueue === 'default') {
            return [];
        }

        $translationWorkerCommand = (string) env('AUTO_TRANSLATION_WORKER_COMMAND', '');

        if ($this->workerCommandIncludesQueue($queueWorkerCommand, $translationQueue)
            || $this->workerCommandIncludesQueue($translationWorkerCommand, $translationQueue)) {
            return [];
        }

        return [[
            'level' => 'warning',
            'key' => 'AUTO_TRANSLATION_WORKER_COMMAND',
            'message' => "AUTO_TRANSLATION_QUEUE is {$translationQueue}, so document a worker command that listens to that queue.",
        ]];
    }

    private function workerCommandIncludesQueue(string $command, string $queue): bool
    {
        if ($command === '' || ! preg_match_all('/--queue(?:=|\s+)([^\s]+)/', $command, $matches)) {
            return false;
        }

        foreach ($matches[1] as $queueList) {
            $queues = array_map('trim', explode(',', $queueList));

            if (in_array($queue, $queues, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{level: string, key: string, message: string}>
     */
    private function uploadStorageChecks(): array
    {
        $backend = (string) env('UPLOAD_STORAGE_BACKEND', '');
        $mountPath = (string) env('UPLOAD_STORAGE_MOUNT_PATH', '');
        $defaultDisk = (string) config('filesystems.default');

        if ($backend === '') {
            return [[
                'level' => 'error',
                'key' => 'UPLOAD_STORAGE_BACKEND',
                'message' => 'Set UPLOAD_STORAGE_BACKEND to mounted_volume before launch, or refactor uploads to dedicated object-storage disks.',
            ]];
        }

        if ($backend === 'mounted_volume') {
            return array_values(array_filter([
                $this->expectPresent('error', 'UPLOAD_STORAGE_MOUNT_PATH', $mountPath, 'UPLOAD_STORAGE_MOUNT_PATH must point to the mounted Hetzner/Coolify upload volume.'),
            ]));
        }

        if ($backend === 's3') {
            return array_values(array_filter([
                $this->expectEquals('warning', 'FILESYSTEM_DISK', $defaultDisk, 's3', 'FILESYSTEM_DISK should be s3 when object storage is the selected upload backend.'),
                $this->expectPresent('error', 'AWS_ACCESS_KEY_ID', (string) config('filesystems.disks.s3.key'), 'AWS_ACCESS_KEY_ID is required for S3-compatible upload storage.'),
                $this->expectPresent('error', 'AWS_SECRET_ACCESS_KEY', (string) config('filesystems.disks.s3.secret'), 'AWS_SECRET_ACCESS_KEY is required for S3-compatible upload storage.'),
                $this->expectPresent('error', 'AWS_BUCKET', (string) config('filesystems.disks.s3.bucket'), 'AWS_BUCKET is required for S3-compatible upload storage.'),
                $this->expectPresent('warning', 'AWS_ENDPOINT', (string) config('filesystems.disks.s3.endpoint'), 'AWS_ENDPOINT should be set for S3-compatible object storage.'),
            ]));
        }

        return [[
            'level' => 'error',
            'key' => 'UPLOAD_STORAGE_BACKEND',
            'message' => 'UPLOAD_STORAGE_BACKEND must be mounted_volume or s3.',
        ]];
    }

    /**
     * @return array<int, array{level: string, key: string, message: string}>
     */
    private function backupChecks(): array
    {
        $retentionDays = (int) env('BACKUP_RETENTION_DAYS', 0);

        return array_values(array_filter([
            $this->expectTrue('error', 'BACKUPS_ENABLED', $this->envBool('BACKUPS_ENABLED'), 'Automated production database backups must be enabled.'),
            $this->expectPresent('error', 'BACKUP_PROVIDER', (string) env('BACKUP_PROVIDER', ''), 'BACKUP_PROVIDER must document where automated backups are managed.'),
            $retentionDays >= 7 ? null : [
                'level' => 'warning',
                'key' => 'BACKUP_RETENTION_DAYS',
                'message' => 'BACKUP_RETENTION_DAYS should be at least 7 before launch.',
            ],
            $this->expectTrue('error', 'BACKUP_RESTORE_DRILL_COMPLETED', $this->envBool('BACKUP_RESTORE_DRILL_COMPLETED'), 'A production-like backup restore drill must be completed before accepting real payments.'),
            $this->expectPresent('warning', 'BACKUP_LAST_RESTORE_DRILL_DATE', (string) env('BACKUP_LAST_RESTORE_DRILL_DATE', ''), 'Record the date of the latest successful restore drill.'),
        ]));
    }

    /**
     * @return array<int, array{level: string, key: string, message: string}>
     */
    private function payFastChecks(): array
    {
        try {
            DB::connection()->getPdo();
            $merchantId = (string) Setting::getValue('payfast.merchant_id', '');
            $merchantKey = (string) Setting::getValue('payfast.merchant_key', '');
            $passphrase = (string) Setting::getValue('payfast.passphrase', '');
            $useSandbox = (string) Setting::getValue('payfast.use_sandbox', '1') === '1';
        } catch (\Throwable $exception) {
            return [[
                'level' => 'warning',
                'key' => 'PAYFAST_SETTINGS',
                'message' => 'PayFast settings could not be checked because the database is unavailable: '.$exception->getMessage(),
            ]];
        }

        return array_values(array_filter([
            $this->expectFalse('error', 'payfast.use_sandbox', $useSandbox, 'PayFast sandbox mode must be disabled before launch.'),
            $this->expectPresent('error', 'payfast.merchant_id', $merchantId, 'PayFast merchant ID must be configured.'),
            $this->expectPresent('error', 'payfast.merchant_key', $merchantKey, 'PayFast merchant key must be configured.'),
            $this->expectNotIn('error', 'payfast.merchant_id', $merchantId, ['10000100'], 'PayFast merchant ID is still the sandbox default.'),
            $this->expectNotIn('error', 'payfast.merchant_key', $merchantKey, ['46f0cd694581a'], 'PayFast merchant key is still the sandbox default.'),
            $this->expectPresent('warning', 'payfast.passphrase', $passphrase, 'Set a PayFast passphrase if your PayFast account uses one.'),
        ]));
    }

    /**
     * @return array<int, array{level: string, key: string, message: string}>
     */
    private function errorTrackingChecks(): array
    {
        $enabled = (bool) config('error_tracking.enabled', false);
        $driver = (string) config('error_tracking.driver', 'log');
        $sampleRate = (float) config('error_tracking.sample_rate', 1.0);

        return array_values(array_filter([
            $this->expectTrue('error', 'ERROR_TRACKING_ENABLED', $enabled, 'ERROR_TRACKING_ENABLED must be true before public launch.'),
            $this->expectNotIn('error', 'ERROR_TRACKING_DRIVER', $driver, ['', 'null', 'none'], 'ERROR_TRACKING_DRIVER must be log or webhook.'),
            $enabled && $driver === 'webhook'
                ? $this->expectPresent('error', 'ERROR_TRACKING_WEBHOOK_URL', (string) config('error_tracking.webhook_url', ''), 'ERROR_TRACKING_WEBHOOK_URL is required when ERROR_TRACKING_DRIVER=webhook.')
                : null,
            $enabled && $driver === 'log'
                ? [
                    'level' => 'warning',
                    'key' => 'ERROR_TRACKING_DRIVER',
                    'message' => 'ERROR_TRACKING_DRIVER=log records exceptions locally; production should use webhook with Sentry, Bugsnag, or an equivalent external incident sink.',
                ]
                : null,
            $enabled && ! in_array($driver, ['log', 'webhook', 'null', 'none'], true)
                ? [
                    'level' => 'error',
                    'key' => 'ERROR_TRACKING_DRIVER',
                    'message' => 'ERROR_TRACKING_DRIVER must be log or webhook.',
                ]
                : null,
            $enabled && ($sampleRate <= 0.0 || $sampleRate > 1.0)
                ? [
                    'level' => 'warning',
                    'key' => 'ERROR_TRACKING_SAMPLE_RATE',
                    'message' => 'ERROR_TRACKING_SAMPLE_RATE should be greater than 0 and no more than 1.',
                ]
                : null,
        ]));
    }

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    private function expectHttpsUrl(): ?array
    {
        $url = (string) config('app.url');

        if ($url === '' || str_contains($url, 'localhost') || ! str_starts_with($url, 'https://')) {
            return [
                'level' => 'error',
                'key' => 'APP_URL',
                'message' => 'APP_URL must be the public HTTPS production URL.',
            ];
        }

        return null;
    }

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    private function expectRealMailFrom(): ?array
    {
        $address = (string) config('mail.from.address');

        if ($address === '' || str_ends_with($address, '@example.com')) {
            return [
                'level' => 'warning',
                'key' => 'MAIL_FROM_ADDRESS',
                'message' => 'MAIL_FROM_ADDRESS should use a monitored production sender.',
            ];
        }

        return null;
    }

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    private function expectEquals(string $level, string $key, string $actual, string $expected, string $message): ?array
    {
        return $actual === $expected ? null : compact('level', 'key', 'message');
    }

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    private function expectPresent(string $level, string $key, string $actual, string $message): ?array
    {
        return trim($actual) !== '' ? null : compact('level', 'key', 'message');
    }

    /**
     * @param array<int, string> $badValues
     *
     * @return array{level: string, key: string, message: string}|null
     */
    private function expectNotIn(string $level, string $key, string $actual, array $badValues, string $message): ?array
    {
        return in_array($actual, $badValues, true) ? compact('level', 'key', 'message') : null;
    }

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    private function expectFalse(string $level, string $key, bool $actual, string $message): ?array
    {
        return $actual ? compact('level', 'key', 'message') : null;
    }

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    private function expectTrue(string $level, string $key, bool $actual, string $message): ?array
    {
        return $actual ? null : compact('level', 'key', 'message');
    }

    private function envBool(string $key): bool
    {
        return filter_var((string) env($key, 'false'), FILTER_VALIDATE_BOOL);
    }
}
