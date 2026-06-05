<?php

namespace App\Support\Monitoring;

use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthReport
{
    private const WARNING_WINDOW_HOURS = 24;

    /**
     * @return array{
     *     status: string,
     *     checked_at: string,
     *     environment: string,
     *     checks: array<string, array{status: string, message: string, meta: array<string, mixed>}>
     * }
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->database(),
            'storage' => $this->storage(),
            'disk' => $this->disk(),
            'queue' => $this->queue(),
            'payments' => $this->payments(),
            'mail' => $this->mail(),
        ];

        return [
            'status' => $this->overallStatus($checks),
            'checked_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{status: string, message: string, meta: array<string, mixed>}
     */
    private function database(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->check('ok', 'Database connection is reachable.', [
                'connection' => (string) config('database.default'),
                'migrations' => $this->tableExists('migrations')
                    ? DB::table('migrations')->count()
                    : null,
            ]);
        } catch (\Throwable $exception) {
            return $this->check('error', 'Database connection failed.', [
                'exception' => class_basename($exception),
            ]);
        }
    }

    /**
     * @return array{status: string, message: string, meta: array<string, mixed>}
     */
    private function storage(): array
    {
        $paths = [
            'storage' => storage_path(),
            'app' => storage_path('app'),
            'framework' => storage_path('framework'),
            'logs' => storage_path('logs'),
        ];

        $unwritable = [];

        foreach ($paths as $label => $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $unwritable[] = $label;
            }
        }

        if ($unwritable !== []) {
            return $this->check('error', 'One or more storage paths are not writable.', [
                'unwritable' => $unwritable,
            ]);
        }

        return $this->check('ok', 'Required storage paths are writable.', [
            'paths_checked' => array_keys($paths),
        ]);
    }

    /**
     * @return array{status: string, message: string, meta: array<string, mixed>}
     */
    private function disk(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if (! is_numeric($free) || ! is_numeric($total) || (float) $total <= 0) {
            return $this->check('warning', 'Disk capacity could not be measured.', []);
        }

        $freePercent = round(((float) $free / (float) $total) * 100, 2);
        $meta = [
            'free_bytes' => (int) $free,
            'total_bytes' => (int) $total,
            'free_percent' => $freePercent,
        ];

        if ($freePercent <= 5.0) {
            return $this->check('error', 'Disk free space is critically low.', $meta);
        }

        if ($freePercent <= 15.0) {
            return $this->check('warning', 'Disk free space is low.', $meta);
        }

        return $this->check('ok', 'Disk free space is within the launch threshold.', $meta);
    }

    /**
     * @return array{status: string, message: string, meta: array<string, mixed>}
     */
    private function queue(): array
    {
        if (! $this->tableExists('failed_jobs')) {
            return $this->check('warning', 'Failed job table is not available for queue monitoring.', [
                'connection' => (string) config('queue.default'),
            ]);
        }

        $failedTotal = DB::table('failed_jobs')->count();
        $failedRecent = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHours(self::WARNING_WINDOW_HOURS))
            ->count();

        $status = $failedRecent > 0 ? 'warning' : 'ok';
        $message = $failedRecent > 0
            ? 'Recent failed queue jobs need review.'
            : 'No recent failed queue jobs.';

        return $this->check($status, $message, [
            'connection' => (string) config('queue.default'),
            'failed_total' => $failedTotal,
            'failed_last_24h' => $failedRecent,
        ]);
    }

    /**
     * @return array{status: string, message: string, meta: array<string, mixed>}
     */
    private function payments(): array
    {
        if (! $this->tableExists('payments')) {
            return $this->check('warning', 'Payments table is not available for payment monitoring.', []);
        }

        $failedRecent = Payment::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(self::WARNING_WINDOW_HOURS))
            ->count();
        $stalePendingOrders = $this->tableExists('orders')
            ? Order::query()
                ->where('status', 'pending_payment')
                ->where('created_at', '<=', now()->subHours(self::WARNING_WINDOW_HOURS))
                ->count()
            : 0;

        $status = ($failedRecent > 0 || $stalePendingOrders > 0) ? 'warning' : 'ok';
        $message = $status === 'warning'
            ? 'Recent failed payments or stale pending orders need review.'
            : 'No recent failed payments or stale pending orders.';

        return $this->check($status, $message, [
            'failed_last_24h' => $failedRecent,
            'pending_orders_older_than_24h' => $stalePendingOrders,
        ]);
    }

    /**
     * @return array{status: string, message: string, meta: array<string, mixed>}
     */
    private function mail(): array
    {
        if (! $this->tableExists('notification_logs')) {
            return $this->check('warning', 'Notification logs are not available for mail monitoring.', []);
        }

        $failedRecent = NotificationLog::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(self::WARNING_WINDOW_HOURS))
            ->count();
        $staleQueued = NotificationLog::query()
            ->whereIn('status', ['pending', 'queued'])
            ->where('created_at', '<=', now()->subHour())
            ->count();

        $status = ($failedRecent > 0 || $staleQueued > 0) ? 'warning' : 'ok';
        $message = $status === 'warning'
            ? 'Recent failed or stale queued notifications need review.'
            : 'No recent failed or stale queued notifications.';

        return $this->check($status, $message, [
            'failed_last_24h' => $failedRecent,
            'queued_older_than_1h' => $staleQueued,
        ]);
    }

    /**
     * @param array<string, array{status: string, message: string, meta: array<string, mixed>}> $checks
     */
    private function overallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('error', $statuses, true)) {
            return 'down';
        }

        if (in_array('warning', $statuses, true)) {
            return 'degraded';
        }

        return 'ok';
    }

    /**
     * @return array{status: string, message: string, meta: array<string, mixed>}
     */
    private function check(string $status, string $message, array $meta = []): array
    {
        return compact('status', 'message', 'meta');
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
