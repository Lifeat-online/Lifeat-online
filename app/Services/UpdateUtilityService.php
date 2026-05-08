<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class UpdateUtilityService
{
    public function status(): array
    {
        $branch = $this->branch();
        $enabled = $this->enabled();
        $base = base_path();

        $currentVersion = $this->currentVersion();
        $gitVersion = $this->run(['git', '--version'], $base, 10);
        $isGitAvailable = $gitVersion['ok'];

        $isRepo = $this->run(['git', 'rev-parse', '--is-inside-work-tree'], $base, 10);
        $isRepository = $isRepo['ok'] && trim((string) $isRepo['output']) === 'true';

        $localHash = null;
        $localBranch = null;
        $isDirty = null;

        if ($isRepository) {
            $localHashRes = $this->run(['git', 'rev-parse', 'HEAD'], $base, 10);
            $localHash = $localHashRes['ok'] ? trim((string) $localHashRes['output']) : null;

            $localBranchRes = $this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $base, 10);
            $localBranch = $localBranchRes['ok'] ? trim((string) $localBranchRes['output']) : null;

            $dirtyRes = $this->run(['git', 'status', '--porcelain'], $base, 10);
            $isDirty = $dirtyRes['ok'] ? trim((string) $dirtyRes['output']) !== '' : null;
        }

        $remoteHash = null;
        $updateAvailable = false;

        if ($enabled && $isGitAvailable && $isRepository) {
            $remoteHash = $this->remoteHeadHash($branch);
            $updateAvailable = is_string($remoteHash) && is_string($localHash) && $remoteHash !== $localHash;
        }

        return [
            'ok' => true,
            'enabled' => $enabled,
            'branch' => $branch,
            'current_version' => $currentVersion,
            'git_available' => $isGitAvailable,
            'is_repository' => $isRepository,
            'local_branch' => $localBranch,
            'local_hash' => $localHash,
            'remote_hash' => $remoteHash,
            'update_available' => $updateAvailable,
            'is_dirty' => $isDirty,
        ];
    }

    public function apply(): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'message' => 'Updater is disabled in this environment.'];
        }

        $lock = Cache::lock('pos-updater:apply', 1200);
        if (! $lock->get()) {
            return ['ok' => false, 'message' => 'Another update is already running.'];
        }

        $base = base_path();
        $branch = $this->branch();
        $allowDirty = filter_var((string) env('POS_UPDATER_ALLOW_DIRTY', 'false'), FILTER_VALIDATE_BOOL);
        $useMaintenance = filter_var((string) env('POS_UPDATER_USE_MAINTENANCE', 'false'), FILTER_VALIDATE_BOOL);

        try {
            $dirtyRes = $this->run(['git', 'status', '--porcelain'], $base, 20);
            if ($dirtyRes['ok'] && trim((string) $dirtyRes['output']) !== '' && ! $allowDirty) {
                return ['ok' => false, 'message' => 'Working tree has uncommitted changes. Commit/stash them or set POS_UPDATER_ALLOW_DIRTY=true.'];
            }

            $log = [];

            if ($useMaintenance) {
                $log[] = $this->runOrThrow(['php', 'artisan', 'down', '--message=Updating application…'], $base, 60);
            }

            $log[] = $this->runOrThrow(['git', 'fetch', 'origin', '--prune'], $base, 300);
            $log[] = $this->runOrThrow(['git', 'checkout', $branch], $base, 60);
            $log[] = $this->runOrThrow(['git', 'pull', '--ff-only', 'origin', $branch], $base, 300);

            $log[] = $this->runOrThrow($this->composerCommand(), $base, 900);
            $log[] = $this->runOrThrow(['php', 'artisan', 'migrate', '--force'], $base, 600);
            $log[] = $this->runOrThrow(['php', 'artisan', 'optimize:clear'], $base, 120);
            $log[] = $this->runOrThrow(['php', 'artisan', 'optimize'], $base, 300);

            if ($useMaintenance) {
                $log[] = $this->runOrThrow(['php', 'artisan', 'up'], $base, 60);
            }

            $finalStatus = $this->status();

            return [
                'ok' => true,
                'message' => 'Update complete.',
                'log' => $log,
                'status' => $finalStatus,
            ];
        } catch (ProcessFailedException $e) {
            if ($useMaintenance) {
                $this->run(['php', 'artisan', 'up'], $base, 60);
            }

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        } finally {
            $lock->release();
        }
    }

    private function enabled(): bool
    {
        $flag = env('POS_UPDATER_ENABLED');
        if ($flag !== null) {
            return filter_var((string) $flag, FILTER_VALIDATE_BOOL);
        }

        return app()->environment('local');
    }

    private function branch(): string
    {
        $branch = (string) env('POS_UPDATE_BRANCH', 'main');
        $branch = trim($branch) !== '' ? trim($branch) : 'main';

        return $branch;
    }

    private function currentVersion(): ?string
    {
        $path = base_path('composer.json');
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        $v = $data['version'] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    private function remoteHeadHash(string $branch): ?string
    {
        $res = $this->run(['git', 'ls-remote', 'origin', 'refs/heads/'.$branch], base_path(), 30);
        if (! $res['ok']) {
            return null;
        }

        $line = trim((string) $res['output']);
        if ($line === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $line);
        $hash = $parts[0] ?? null;

        return is_string($hash) && trim($hash) !== '' ? trim($hash) : null;
    }

    private function composerCommand(): array
    {
        $args = ['composer', 'install', '--no-interaction'];

        if (! app()->environment('local')) {
            $args = array_merge($args, ['--no-dev', '--prefer-dist', '--optimize-autoloader']);
        }

        return $args;
    }

    private function runOrThrow(array $command, string $cwd, int $timeoutSeconds): array
    {
        $res = $this->run($command, $cwd, $timeoutSeconds);
        if (! $res['ok']) {
            throw new ProcessFailedException($res['process']);
        }

        return [
            'command' => $command,
            'exit_code' => $res['exit_code'],
            'output' => $res['output'],
            'error_output' => $res['error_output'],
        ];
    }

    private function run(array $command, string $cwd, int $timeoutSeconds): array
    {
        $process = new Process($command, $cwd);
        $process->setTimeout($timeoutSeconds);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => $this->sanitizeOutput($process->getOutput()),
            'error_output' => $this->sanitizeOutput($process->getErrorOutput()),
            'process' => $process,
        ];
    }

    private function sanitizeOutput(string $text): string
    {
        $token = (string) env('POS_UPDATE_GITHUB_TOKEN', '');
        if ($token !== '') {
            $text = str_replace($token, '[redacted]', $text);
        }

        return $text;
    }
}
