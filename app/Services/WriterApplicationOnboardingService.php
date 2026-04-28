<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\WriterApplication;
use Illuminate\Support\Str;

class WriterApplicationOnboardingService
{
    public function onboard(WriterApplication $application, string $assignedRole): User
    {
        $user = $application->user
            ?: User::query()->where('email', $application->email)->first()
            ?: User::query()->where('username', $application->username)->first()
            ?: new User();

        $isNewUser = ! $user->exists;

        $user->fill([
            'name' => $application->fullName(),
            'email' => $application->email,
            'username' => $application->username,
            'phone' => $application->phone,
            'bio' => $application->profile_bio,
        ]);

        if ($isNewUser) {
            $user->password = Str::random(40);
            $user->role = $assignedRole;
        } elseif ($this->shouldPromotePrimaryRole($user)) {
            $user->role = $assignedRole;
        }

        $user->save();

        if ($role = Role::query()->where('slug', $this->roleSlug($assignedRole))->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $application->user()->associate($user);

        return $user;
    }

    private function shouldPromotePrimaryRole(User $user): bool
    {
        return in_array($user->role, [null, 'member', 'writer', 'staff'], true);
    }

    private function roleSlug(string $assignedRole): string
    {
        return $assignedRole === 'staff' ? 'sales_staff' : 'writer';
    }
}
