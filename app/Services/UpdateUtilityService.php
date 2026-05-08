<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class UpdateUtilityService
{
    public function credentialsStatus(): array
    {
        $base = base_path();
        $isRepo = $this->run(['git', 'rev-parse', '--is-inside-work-tree'], $base, 10);
        $isRepository = $isRepo['ok'] && trim((string) $isRepo['output']) === 'true';

        $originUrl = null;
        if ($isRepository) {
            $origin = $this->run(['git', 'remote', 'get-url', 'origin'], $base, 10);
            $originUrl = $origin['ok'] ? trim((string) $origin['output']) : null;
        }

        $hasToken = $this->hasStoredToken();

        $authMode = 'none';
        if (is_string($originUrl) && (str_starts_with($originUrl, 'git@') || str_starts_with($originUrl, 'ssh://'))) {
            $authMode = 'ssh';
        } elseif ($hasToken) {
            $authMode = 'token';
        }

        return [
            'ok' => true,
            'is_repository' => $isRepository,
            'origin_url' => $originUrl,
            'has_token' => $hasToken,
            'auth_mode' => $authMode,
        ];
    }

    public function saveCredentials(?string $originUrl, ?string $token, bool $clearToken): array
    {
        $base = base_path();

        $isRepo = $this->run(['git', 'rev-parse', '--is-inside-work-tree'], $base, 10);
        $isRepository = $isRepo['ok'] && trim((string) $isRepo['output']) === 'true';

        if (! $isRepository) {
            return ['ok' => false, 'message' => 'Not a git repository.'];
        }

        if (is_string($originUrl) && trim($originUrl) !== '') {
            $originUrl = trim($originUrl);
            if (! $this->isValidOriginUrl($originUrl)) {
                return ['ok' => false, 'message' => 'Origin URL must be a valid git repository URL (SSH or HTTPS).'];
            }

            $set = $this->run(['git', 'remote', 'set-url', 'origin', $originUrl], $base, 20);
            if (! $set['ok']) {
                return ['ok' => false, 'message' => trim((string) $set['error_output']) !== '' ? trim((string) $set['error_output']) : 'Failed to set origin URL.'];
            }
        }

        if ($clearToken) {
            $this->clearStoredToken();
        } elseif (is_string($token) && trim($token) !== '') {
            $this->storeToken(trim($token));
        }

        return $this->credentialsStatus();
    }

    public function isValidOriginUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        return $this->isValidGitRemoteUrl($url);
    }

    public function testRemoteAccess(): array
    {
        $base = base_path();
        $branch = $this->branch();

        $ctx = $this->gitAuthContext();
        try {
            $res = $this->run(
                ['git', 'ls-remote', 'origin', 'refs/heads/'.$branch],
                $base,
                30,
                $ctx['env'],
                $ctx['redact']
            );

            return [
                'ok' => $res['ok'],
                'exit_code' => $res['exit_code'],
                'output' => $res['output'],
                'error_output' => $res['error_output'],
            ];
        } finally {
            $this->cleanupFiles($ctx['cleanup']);
        }
    }

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

        $credentials = $this->credentialsStatus();

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
            'origin_url' => $credentials['origin_url'] ?? null,
            'has_token' => $credentials['has_token'] ?? false,
            'auth_mode' => $credentials['auth_mode'] ?? 'none',
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

        $gitCtx = $this->gitAuthContext();

        try {
            $dirtyRes = $this->run(['git', 'status', '--porcelain'], $base, 20, $gitCtx['env'], $gitCtx['redact']);
            if ($dirtyRes['ok'] && trim((string) $dirtyRes['output']) !== '' && ! $allowDirty) {
                return ['ok' => false, 'message' => 'Working tree has uncommitted changes. Commit/stash them or set POS_UPDATER_ALLOW_DIRTY=true.'];
            }

            $log = [];

            if ($useMaintenance) {
                $log[] = $this->runOrThrow(['php', 'artisan', 'down', '--message=Updating application…'], $base, 60);
            }

            $log[] = $this->runOrThrow(['git', 'fetch', 'origin', '--prune', '--tags'], $base, 300, $gitCtx['env'], $gitCtx['redact']);

            $hasBranch = $this->run(['git', 'show-ref', '--verify', '--quiet', 'refs/heads/'.$branch], $base, 20, $gitCtx['env'], $gitCtx['redact']);
            if ($hasBranch['ok']) {
                $log[] = $this->runOrThrow(['git', 'checkout', $branch], $base, 60, $gitCtx['env'], $gitCtx['redact']);
            } else {
                $log[] = $this->runOrThrow(['git', 'checkout', '-B', $branch, 'origin/'.$branch], $base, 60, $gitCtx['env'], $gitCtx['redact']);
            }
            $log[] = $this->runOrThrow(['git', 'pull', '--ff-only', 'origin', $branch], $base, 300, $gitCtx['env'], $gitCtx['redact']);

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
            $this->cleanupFiles($gitCtx['cleanup']);
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
        $ctx = $this->gitAuthContext();
        try {
            $res = $this->run(['git', 'ls-remote', 'origin', 'refs/heads/'.$branch], base_path(), 30, $ctx['env'], $ctx['redact']);
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
        } finally {
            $this->cleanupFiles($ctx['cleanup']);
        }
    }

    private function composerCommand(): array
    {
        $args = ['composer', 'install', '--no-interaction'];

        if (! app()->environment('local')) {
            $args = array_merge($args, ['--no-dev', '--prefer-dist', '--optimize-autoloader']);
        }

        return $args;
    }

    private function runOrThrow(array $command, string $cwd, int $timeoutSeconds, ?array $env = null, array $redact = []): array
    {
        $res = $this->run($command, $cwd, $timeoutSeconds, $env, $redact);
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

    private function run(array $command, string $cwd, int $timeoutSeconds, ?array $env = null, array $redact = []): array
    {
        $mergedEnv = $env === null ? null : array_merge($_SERVER, $_ENV, $env);
        $process = new Process($command, $cwd, $mergedEnv);
        $process->setTimeout($timeoutSeconds);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => $this->sanitizeOutput($process->getOutput(), $redact),
            'error_output' => $this->sanitizeOutput($process->getErrorOutput(), $redact),
            'process' => $process,
        ];
    }

    private function sanitizeOutput(string $text, array $redact): string
    {
        foreach ($redact as $secret) {
            if (! is_string($secret) || $secret === '') {
                continue;
            }
            $text = str_replace($secret, '[redacted]', $text);
        }
        return $text;
    }

    private function hasStoredToken(): bool
    {
        return is_string($this->storedToken()) && $this->storedToken() !== '';
    }

    private function storeToken(string $token): void
    {
        $dir = $this->credentialsDirectory();
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $payload = [
            'github_token' => Crypt::encryptString($token),
        ];

        @file_put_contents($this->credentialsPath(), json_encode($payload), LOCK_EX);
    }

    private function clearStoredToken(): void
    {
        $path = $this->credentialsPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function storedToken(): ?string
    {
        $path = $this->credentialsPath();
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

        $encrypted = $data['github_token'] ?? null;
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function credentialsDirectory(): string
    {
        return storage_path('app/pos-updater');
    }

    private function credentialsPath(): string
    {
        return $this->credentialsDirectory().DIRECTORY_SEPARATOR.'credentials.json';
    }

    private function gitAuthContext(): array
    {
        $env = [
            'GIT_TERMINAL_PROMPT' => '0',
        ];
        $cleanup = [];
        $redact = [];

        $token = $this->storedToken();
        if (is_string($token) && trim($token) !== '') {
            $askpass = $this->createAskpassScript();
            $env['GIT_ASKPASS'] = $askpass;
            $env['POS_UPDATE_GITHUB_TOKEN'] = $token;
            $cleanup[] = $askpass;
            $redact[] = $token;
        }

        $originUrl = $this->originUrl();
        if ($this->isSshOrigin($originUrl)) {
            $env = array_merge($env, $this->sshGitEnv());
        }

        return [
            'env' => $env,
            'cleanup' => $cleanup,
            'redact' => $redact,
        ];
    }

    private function isValidGitRemoteUrl(string $url): bool
    {
        if (preg_match('/\s/', $url) === 1) {
            return false;
        }

        return $this->isValidSshGitUrl($url) || $this->isValidHttpsGitUrl($url);
    }

    private function isValidSshGitUrl(string $url): bool
    {
        if (str_starts_with($url, 'ssh://')) {
            $parts = parse_url($url);
            if (! is_array($parts)) {
                return false;
            }

            $host = $parts['host'] ?? '';
            $path = $parts['path'] ?? '';
            $query = $parts['query'] ?? null;
            $fragment = $parts['fragment'] ?? null;
            if (! is_string($host) || trim($host) === '') {
                return false;
            }
            if (! is_string($path) || trim($path) === '') {
                return false;
            }
            if ($query !== null || $fragment !== null) {
                return false;
            }

            return $this->hasOwnerRepoPath($path);
        }

        if (preg_match('/^[A-Za-z0-9_.-]+@[A-Za-z0-9_.-]+:[A-Za-z0-9_.~\\/-]+$/', $url) !== 1) {
            return false;
        }

        $afterColon = explode(':', $url, 2)[1] ?? '';

        return $this->hasOwnerRepoPath($afterColon);
    }

    private function isValidHttpsGitUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? null;
        $fragment = $parts['fragment'] ?? null;

        if (! in_array($scheme, ['https', 'http'], true)) {
            return false;
        }
        if (! is_string($host) || trim($host) === '') {
            return false;
        }
        if (! is_string($path) || trim($path) === '') {
            return false;
        }
        if ($query !== null || $fragment !== null) {
            return false;
        }

        return $this->hasOwnerRepoPath($path);
    }

    private function hasOwnerRepoPath(string $path): bool
    {
        $path = trim($path);
        $path = ltrim($path, '/');
        $path = preg_replace('#/+$#', '', $path);

        if ($path === '') {
            return false;
        }

        if (str_ends_with($path, '.git')) {
            $path = substr($path, 0, -4);
        }

        $segments = array_values(array_filter(explode('/', $path), fn ($s) => is_string($s) && $s !== ''));

        return count($segments) >= 2;
    }

    private function originUrl(): ?string
    {
        $base = base_path();
        $res = $this->run(['git', 'remote', 'get-url', 'origin'], $base, 10, ['GIT_TERMINAL_PROMPT' => '0']);
        if (! $res['ok']) {
            return null;
        }

        $url = trim((string) $res['output']);

        return $url !== '' ? $url : null;
    }

    private function isSshOrigin(?string $originUrl): bool
    {
        if (! is_string($originUrl) || trim($originUrl) === '') {
            return false;
        }

        $originUrl = trim($originUrl);

        return str_starts_with($originUrl, 'git@') || str_starts_with($originUrl, 'ssh://');
    }

    private function sshGitEnv(): array
    {
        $knownHosts = $this->ensureSshKnownHostsFile();
        $knownHosts = str_replace('\\', '/', $knownHosts);

        $strict = strtolower(trim((string) env('POS_UPDATER_SSH_STRICT', 'accept-new')));
        if (! in_array($strict, ['accept-new', 'yes', 'no'], true)) {
            $strict = 'accept-new';
        }

        return [
            'GIT_SSH_COMMAND' => 'ssh -o UserKnownHostsFile="'.$knownHosts.'" -o StrictHostKeyChecking='.$strict,
        ];
    }

    private function ensureSshKnownHostsFile(): string
    {
        $dir = $this->credentialsDirectory().DIRECTORY_SEPARATOR.'ssh';
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.'known_hosts';
        if (! is_file($path)) {
            @file_put_contents($path, '', LOCK_EX);
        }

        return $path;
    }

    private function createAskpassScript(): string
    {
        $dir = $this->credentialsDirectory();
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $name = 'askpass-'.bin2hex(random_bytes(12)).'.cmd';
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        $script = implode("\r\n", [
            '@echo off',
            'set p=%*',
            'echo %p%| findstr /i "username" >nul',
            'if %errorlevel%==0 (echo x-access-token) else (echo %POS_UPDATE_GITHUB_TOKEN%)',
        ]);

        @file_put_contents($path, $script, LOCK_EX);

        return $path;
    }

    private function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
