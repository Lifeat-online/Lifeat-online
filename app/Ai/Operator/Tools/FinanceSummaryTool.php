<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;

class FinanceSummaryTool implements OperatorTool
{
    public function name(): string
    {
        return 'finance.summary';
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
            'payments' => Payment::query()->count(),
            'successful_payments' => Payment::query()->where('status', 'paid')->count(),
            'active_subscriptions' => Subscription::query()->where('status', 'active')->count(),
        ];
    }
}
