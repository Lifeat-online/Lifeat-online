<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\User;

class UserSummaryTool implements OperatorTool
{
    public function name(): string
    {
        return 'users.summary';
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
        return $user->hasRole('admin', 'support', 'dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return 'read-only';
    }

    public function execute(User $user, array $arguments): array
    {
        return [
            'total' => User::query()->count(),
            'verified' => User::query()->whereNotNull('email_verified_at')->count(),
            'created_last_30_days' => User::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }
}
