<?php

namespace App\Policies;

use App\Models\AdCampaign;
use App\Models\User;

class AdCampaignPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('admin', 'super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin', 'editor', 'staff', 'support', 'dev', 'developer');
    }

    public function view(User $user, AdCampaign $adCampaign): bool
    {
        if ((int) $adCampaign->user_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('editor', 'staff', 'support', 'dev', 'developer');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AdCampaign $adCampaign): bool
    {
        if ((int) $adCampaign->user_id === (int) $user->id) {
            return in_array($adCampaign->status, [AdCampaign::STATUS_DRAFT, AdCampaign::STATUS_READY, AdCampaign::STATUS_PAUSED], true);
        }

        return $user->hasRole('editor');
    }

    public function delete(User $user, AdCampaign $adCampaign): bool
    {
        if ((int) $adCampaign->user_id === (int) $user->id) {
            return $adCampaign->status === AdCampaign::STATUS_DRAFT;
        }

        return $user->hasRole('admin', 'super_admin', 'editor');
    }

    public function pause(User $user, AdCampaign $adCampaign): bool
    {
        return $this->update($user, $adCampaign) || $user->hasRole('support');
    }

    public function approve(User $user, AdCampaign $adCampaign): bool
    {
        return $user->hasRole('admin', 'super_admin', 'editor');
    }
}
