<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class DevTestRunnerService
{
    public function run(string $suite): array
    {
        $lock = Cache::lock('dev-test-runner:lock', 1200);
        if (! $lock->get()) {
            return [
                'ok' => false,
                'message' => 'A test run is already in progress.',
            ];
        }

        try {
            $command = ['php', 'artisan', 'test'];

            if ($suite !== 'all') {
                $command[] = '--testsuite='.$suite;
            }

            $process = new Process($command, base_path());
            $process->setTimeout(900);

            $startedAt = microtime(true);
            $process->run();
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());
            $combinedOutput = trim($output.($errorOutput !== '' ? PHP_EOL.PHP_EOL.$errorOutput : ''));
            $combinedOutput = $this->truncate($combinedOutput, 20000);

            return [
                'ok' => $process->isSuccessful(),
                'suite' => $suite,
                'command' => implode(' ', $command),
                'exit_code' => $process->getExitCode(),
                'duration_ms' => $durationMs,
                'output' => $combinedOutput !== '' ? $combinedOutput : 'No test output returned.',
            ];
        } finally {
            $lock->release();
        }
    }

    private function truncate(string $value, int $maxCharacters): string
    {
        if ($maxCharacters < 1) {
            return '';
        }

        if (mb_strlen($value) <= $maxCharacters) {
            return $value;
        }

        return mb_substr($value, 0, $maxCharacters).'…';
    }
}

