<?php

namespace App\Services;

use App\Models\Setting;
use Minishlink\WebPush\VAPID;

class VapidKeySetupService
{
    public function status(): array
    {
        $publicKey = $this->publicKey();
        $privateKey = $this->privateKey();

        return [
            'configured' => filled($publicKey) && filled($privateKey),
            'public_key_configured' => filled($publicKey),
            'private_key_configured' => filled($privateKey),
            'subject' => $this->subject(),
            'storage_ready' => true,
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

        $keys = VAPID::createVapidKeys();
        $subject = $this->subject();

        $this->setSetting('webpush.vapid_subject', $subject);
        $this->setSetting('webpush.vapid_public_key', $keys['publicKey']);
        $this->setSetting('webpush.vapid_private_key', $keys['privateKey']);

        config([
            'services.webpush.subject' => $subject,
            'services.webpush.public_key' => $keys['publicKey'],
            'services.webpush.private_key' => $keys['privateKey'],
        ]);

        return [
            ...$this->status(),
            'changed' => true,
            'message' => 'VAPID keys were generated and saved to app settings.',
        ];
    }

    public function subject(): string
    {
        return (string) Setting::getValue('webpush.vapid_subject', config('services.webpush.subject') ?: config('app.url'));
    }

    public function publicKey(): ?string
    {
        return config('services.webpush.public_key') ?: Setting::getValue('webpush.vapid_public_key');
    }

    public function privateKey(): ?string
    {
        return config('services.webpush.private_key') ?: Setting::getValue('webpush.vapid_private_key');
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => 'string',
                'group' => 'webpush',
            ],
        );
    }
}
