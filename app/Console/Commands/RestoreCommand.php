<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RestoreCommand extends Command
{
    protected $signature = 'backup:restore
        {archive : Absolute path to a .sql.gz archive, or filename inside the local db/ directory}
        {--from-s3= : Optional S3 key (e.g. db/lifeat-2026-06-05_020000.sql.gz) to download first}
        {--yes : Skip the confirmation prompt}';

    protected $description = 'Restore a database backup produced by the backup pipeline.';

    public function handle(): int
    {
        $scriptsPath = base_path('scripts/backup');
        $restoreScript = "{$scriptsPath}/restore-db.sh";

        if (! File::exists($restoreScript)) {
            $this->error("Restore script not found at {$restoreScript}");

            return self::FAILURE;
        }

        $archive = (string) $this->argument('archive');
        if (! str_starts_with($archive, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Z]:[\\\\\\/]/i', $archive)) {
            $localDir = config('backup.local_path').'/db';
            $candidate = $localDir.DIRECTORY_SEPARATOR.$archive;
            if (File::exists($candidate)) {
                $archive = $candidate;
            }
        }

        $cmd = escapeshellarg($restoreScript).' '.escapeshellarg($archive);
        if ($fromS3 = $this->option('from-s3')) {
            $cmd .= ' --from-s3 '.escapeshellarg((string) $fromS3);
        }
        if ($this->option('yes')) {
            $cmd .= ' --yes';
        }

        $this->info("Running: {$cmd}");

        $process = Process::fromShellCommandline("{$cmd} 2>&1", base_path());
        $process->setTimeout(7200);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
