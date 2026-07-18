<?php

namespace App\Ai\PublicAssistant;

use App\Models\Setting;
use App\Models\User;

class PublicAssistantAccess
{
    public function allowed(?User $user): bool
    {
        if ($user?->hasRole('dev', 'developer')) {
            return true;
        }

        if (! config('ai_platform.public_chat.enabled')) {
            return false;
        }

        if ($user) {
            return $this->settingEnabled('ai_public_chat.authenticated_enabled');
        }

        return config('ai_platform.public_chat.anonymous_enabled')
            && $this->settingEnabled('ai_public_chat.anonymous_enabled');
    }

    private function settingEnabled(string $key): bool
    {
        return filter_var(Setting::getValue($key, '0'), FILTER_VALIDATE_BOOL);
    }
}
