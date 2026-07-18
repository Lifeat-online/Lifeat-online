<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\AiGeneration;
use App\Models\AiRetrievalTrace;
use App\Models\User;

class AiOperationsSummaryTool implements OperatorTool
{
    public function name(): string
    {
        return 'ai.operations_summary';
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
            'generations_last_24_hours' => AiGeneration::query()->where('created_at', '>=', now()->subDay())->count(),
            'failed_generations_last_24_hours' => AiGeneration::query()->where('created_at', '>=', now()->subDay())->where('status', 'failed')->count(),
            'retrievals_last_24_hours' => AiRetrievalTrace::query()->where('created_at', '>=', now()->subDay())->count(),
        ];
    }
}
