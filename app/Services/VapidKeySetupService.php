<?php

namespace App\Services;

use Minishlink\WebPush\VAPID;
use RuntimeException;

class VapidKeySetupService
{
    public function status(): array
    {
        return [
            'configured' => filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key')),
            'public_key_configured' => filled(config('services.webpush.public_key')),
            'private_key_configured' => filled(config('services.webpush.private_key')),
            'subject' => config('services.webpush.subject') ?: config('app.url'),
            'env_writable' => $this->envWritable(),
        ];
    }

    public function enable(): array
    {
        $status = $this->status();

        if ($status['configured']) {
            return [
                ...$status,
                'changed' => false,
                'message' => 'VAPID keys are already configured.',
            ];
        }

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            throw new RuntimeException('The .env file was not found on this server.');
        }

        if (! is_writable($envPath)) {
            throw new RuntimeException('The .env file is not writable by the web process.');
        }

        $keys = VAPID::createVapidKeys();
        $subject = config('services.webpush.subject') ?: config('app.url');

        $contents = (string) file_get_contents($envPath);
        $contents = $this->setEnvValue($contents, 'WEBPUSH_VAPID_SUBJECT', $subject);
        $contents = $this->setEnvValue($contents, 'WEBPUSH_VAPID_PUBLIC_KEY', $keys['publicKey']);
        $contents = $this->setEnvValue($contents, 'WEBPUSH_VAPID_PRIVATE_KEY', $keys['privateKey']);

        file_put_contents($envPath, $contents);
        $configCacheCleared = $this->clearConfigCache();

        config([
            'services.webpush.subject' => $subject,
            'services.webpush.public_key' => $keys['publicKey'],
            'services.webpush.private_key' => $keys['privateKey'],
        ]);

        return [
            ...$this->status(),
            'changed' => true,
            'config_cache_cleared' => $configCacheCleared,
            'message' => 'VAPID keys were generated and saved to .env.',
        ];
    }

    private function envWritable(): bool
    {
        $envPath = base_path('.env');

        return file_exists($envPath) && is_writable($envPath);
    }

    private function setEnvValue(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$this->quoteEnvValue($value);

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents) === 1) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents) ?? $contents;
        }

        return rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }

    private function clearConfigCache(): bool
    {
        $configCachePath = base_path('bootstrap/cache/config.php');

        if (! file_exists($configCachePath)) {
            return false;
        }

        if (! is_writable($configCachePath)) {
            throw new RuntimeException('VAPID keys were saved, but bootstrap/cache/config.php is not writable. Clear the Laravel config cache before using push prompts.');
        }

        return unlink($configCachePath);
    }

    private function quoteEnvValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/\s|#|"|\'/', $value) !== 1) {
            return $value;
        }

        return '"'.str_replace('"', '\"', $value).'"';
    }
}
