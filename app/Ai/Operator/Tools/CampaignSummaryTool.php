<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\AdCampaign;
use App\Models\PushCampaign;
use App\Models\User;

class CampaignSummaryTool implements OperatorTool
{
    public function name(): string
    {
        return 'campaigns.summary';
    }

    public function risk(): string
    {
        return 'R0';
    }

    public function rules(): array
    {
        return [];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('admin', 'editor', 'support', 'dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return 'read-only';
    }

    public function execute(User $user, array $arguments): array
    {
        return [
            'ad_campaigns' => AdCampaign::query()->count(),
            'active_ad_campaigns' => AdCampaign::query()->where('status', 'active')->count(),
            'push_campaigns' => PushCampaign::query()->count(),
            'scheduled_push_campaigns' => PushCampaign::query()->where('status', 'scheduled')->count(),
        ];
    }
}
