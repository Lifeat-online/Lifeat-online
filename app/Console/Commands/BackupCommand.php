<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupCommand extends Command
{
    protected $signature = 'backup:run
        {--type=all : all|db|storage}
        {--no-upload : Skip the S3 upload step even when configured}
        {--no-prune : Skip the retention prune step}';

    protected $description = 'Run the Hetzner backup pipeline (database and/or storage) via the shell scripts.';

    public function handle(): int
    {
        $scriptsPath = base_path('scripts/backup');
        if (! File::isDirectory($scriptsPath)) {
            $this->error("Backup scripts directory not found: {$scriptsPath}");

            return self::FAILURE;
        }

        $type = (string) $this->option('type');
        if (! in_array($type, ['all', 'db', 'storage'], true)) {
            $this->error("Invalid --type '{$type}'. Use one of: all, db, storage.");

            return self::FAILURE;
        }

        $envOverrides = [];
        if ($this->option('no-upload')) {
            $envOverrides['BACKUP_S3_ENABLED'] = 'false';
        }
        if ($this->option('no-prune')) {
            // Pruning is opt-out by setting retention to 0 in the child process
            // (rotate-backups.sh treats 0 as 'do not prune').
            $envOverrides['BACKUP_RETENTION_DAYS'] = '0';
        }

        $envString = collect($envOverrides)
            ->map(fn ($v, $k) => "{$k}={$v}")
            ->implode(' ');

        $ran = false;
        $exit = self::SUCCESS;

        if ($type === 'all' || $type === 'db') {
            $ran = true;
            $this->info('Running database backup…');
            $exit = max($exit, $this->runScript("{$scriptsPath}/backup-db.sh", $envString));
        }

        if ($type === 'all' || $type === 'storage') {
            $ran = true;
            $this->info('Running storage backup…');
            $exit = max($exit, $this->runScript("{$scriptsPath}/backup-storage.sh", $envString));
        }

        if ($ran && ! $this->option('no-prune')) {
            $this->info('Pruning old backups…');
            $exit = max($exit, $this->runScript("{$scriptsPath}/rotate-backups.sh", $envString));
        }

        if ($exit !== self::SUCCESS && (bool) config('ops.enabled', false)) {
            try {
                app(\App\Services\OperatorPushNotifier::class)->send(
                    target: 'backup:failed',
                    title: 'Backup failed',
                    body: sprintf('backup:run exited with code %d. Check /var/log/lifeat/backup-*.log on the host.', $exit),
                    severity: 'critical',
                    url: 'https://lifeat.online/admin/dashboard',
                    data: ['exit_code' => $exit, 'ran' => $ran, 'type' => $type],
                );
            } catch (\Throwable $e) {
                $this->warn('Could not dispatch backup:failed push: '.$e->getMessage());
            }
        }

        return $exit;
    }

    private function runScript(string $path, string $envString): int
    {
        $cmd = trim("{$envString} {$path}").' 2>&1';
        $this->line("  → {$cmd}");

        $process = Process::fromShellCommandline($cmd, base_path());
        $process->setTimeout(3600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error("Script exited with code {$process->getExitCode()}");
        }

        return $process->getExitCode() ?? 1;
    }
}
