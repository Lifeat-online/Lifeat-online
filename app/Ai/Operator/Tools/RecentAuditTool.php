<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\AuditLog;
use App\Models\User;

class RecentAuditTool implements OperatorTool
{
    public function name(): string
    {
        return 'audits.recent';
    }

    public function risk(): string
    {
        return 'R0';
    }

    public function rules(): array
    {
        return ['limit' => ['sometimes', 'integer', 'min:1', 'max:50']];
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
        $logs = AuditLog::query()->latest('id')->limit((int) ($arguments['limit'] ?? 10))
            ->get(['id', 'actor_user_id', 'action', 'subject_type', 'subject_id', 'created_at']);

        return ['count' => $logs->count(), 'events' => $logs->map(fn (AuditLog $log): array => [
            'id' => $log->id,
            'actor_user_id' => $log->actor_user_id,
            'action' => $log->action,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->all()];
    }
}
