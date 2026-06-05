<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupListCommand extends Command
{
    protected $signature = 'backup:list {--type=db : db|storage}';

    protected $description = 'List the local backup archives available for restore.';

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $subdir = $type === 'storage' ? 'storage' : 'db';
        $path = config('backup.local_path').'/'.$subdir;

        if (! File::isDirectory($path)) {
            $this->warn("No backups directory at {$path}");

            return self::SUCCESS;
        }

        $files = collect(File::files($path))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        if ($files->isEmpty()) {
            $this->info("No {$type} backups found in {$path}");

            return self::SUCCESS;
        }

        $this->info("Available {$type} backups in {$path}:");
        $this->table(
            ['File', 'Size', 'Modified'],
            $files->map(fn ($f) => [
                $f->getFilename(),
                $this->humanSize($f->getSize()),
                date('Y-m-d H:i:s', $f->getMTime()),
            ])->all()
        );

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
