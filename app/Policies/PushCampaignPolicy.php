<?php

namespace App\Policies;

use App\Models\PushCampaign;
use App\Models\User;

class PushCampaignPolicy
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

    public function view(User $user, PushCampaign $pushCampaign): bool
    {
        if ((int) $pushCampaign->user_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('editor', 'staff', 'support', 'dev', 'developer');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'super_admin', 'editor', 'dev', 'developer');
    }

    public function update(User $user, PushCampaign $pushCampaign): bool
    {
        if ((int) $pushCampaign->user_id === (int) $user->id) {
            return in_array($pushCampaign->status, [PushCampaign::STATUS_DRAFT, PushCampaign::STATUS_READY, PushCampaign::STATUS_SCHEDULED], true);
        }

        return $user->hasRole('admin', 'super_admin', 'editor');
    }

    public function delete(User $user, PushCampaign $pushCampaign): bool
    {
        if ((int) $pushCampaign->user_id === (int) $user->id) {
            return in_array($pushCampaign->status, [PushCampaign::STATUS_DRAFT, PushCampaign::STATUS_READY], true);
        }

        return $user->hasRole('admin', 'super_admin', 'editor');
    }

    public function dispatch(User $user, PushCampaign $pushCampaign): bool
    {
        return $this->update($user, $pushCampaign) || $user->hasRole('support');
    }
}
