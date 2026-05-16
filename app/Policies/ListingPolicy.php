<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function manage(User $user, Listing $listing): bool
    {
        return $this->own($user, $listing) || $this->manageAssigned($user, $listing);
    }

    public function own(User $user, Listing $listing): bool
    {
        return (int) $listing->user_id === (int) $user->id;
    }

    public function manageAssigned(User $user, Listing $listing): bool
    {
        return $user->hasRole('staff')
            && (int) $listing->registered_by_user_id === (int) $user->id;
    }

    public function startCheckout(User $user, Listing $listing): bool
    {
        return $this->own($user, $listing)
            || $user->hasRole('admin', 'staff');
    }
}
