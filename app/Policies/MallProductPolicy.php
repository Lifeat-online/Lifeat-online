<?php

namespace App\Policies;

use App\Models\MallProduct;
use App\Models\User;

class MallProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MallProduct $product): bool
    {
        if ($product->store?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin', 'editor', 'mall_admin') || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $this->ownsActiveStore($user);
    }

    public function update(User $user, MallProduct $product): bool
    {
        if ($product->store?->owner_user_id === $user->id) {
            return in_array($product->store->status, ['active', 'pending'], true);
        }

        return $user->hasRole('admin');
    }

    public function delete(User $user, MallProduct $product): bool
    {
        if ($product->store?->owner_user_id === $user->id) {
            return true;
        }

        return $user->hasRole('admin');
    }

    private function ownsActiveStore(User $user): bool
    {
        return $user->mallStore?->status === 'active';
    }
}
