<?php

namespace App\Console\Commands;

use App\Services\OperatorPushNotifier;
use Illuminate\Console\Command;

class OpsCheckDiskCommand extends Command
{
    protected $signature = 'ops:check-disk
        {--path= : Override the path to measure (default: config(ops.disk.path))}';

    protected $description = 'Check disk usage on the application volume and push a warning/critical alert to operators.';

    public function handle(OperatorPushNotifier $notifier): int
    {
        $path = (string) ($this->option('path') ?: config('ops.disk.path', '/app'));

        if (! is_dir($path)) {
            $this->error("Path does not exist: {$path}");

            return self::FAILURE;
        }

        $total = (int) disk_total_space($path);
        $free = (int) disk_free_space($path);

        if ($total <= 0) {
            $this->error("Could not measure disk space for {$path}.");

            return self::FAILURE;
        }

        $usedPercent = (int) round((($total - $free) / $total) * 100);
        $used = $total - $free;

        $this->info(sprintf(
            'Disk %s: %d%% used (%s of %s)',
            $path,
            $usedPercent,
            $this->humanSize($used),
            $this->humanSize($total)
        ));

        $warningAt = (int) config('ops.disk.warning_percent', 80);
        $criticalAt = (int) config('ops.disk.critical_percent', 95);

        if ($usedPercent >= $criticalAt) {
            $notifier->send(
                target: 'disk:critical',
                title: 'Disk space CRITICAL',
                body: sprintf('%s is at %d%%. Free %s immediately.', $path, $usedPercent, $this->humanSize($free)),
                severity: 'critical',
                url: 'https://lifeat.online/admin/dashboard',
                data: ['path' => $path, 'used_percent' => $usedPercent, 'free_bytes' => $free]
            );
        } elseif ($usedPercent >= $warningAt) {
            $notifier->send(
                target: 'disk:warning',
                title: 'Disk space warning',
                body: sprintf('%s is at %d%%. Plan capacity.', $path, $usedPercent),
                severity: 'warning',
                url: 'https://lifeat.online/admin/dashboard',
                data: ['path' => $path, 'used_percent' => $usedPercent, 'free_bytes' => $free]
            );
        }

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
