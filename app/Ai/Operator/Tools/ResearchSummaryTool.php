<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\EditorialDossier;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\SourceSnapshot;
use App\Models\User;

class ResearchSummaryTool implements OperatorTool
{
    public function name(): string
    {
        return 'research.summary';
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
            'active_sources' => ResearchSource::query()->where('is_active', true)->count(),
            'research_items' => ResearchItem::query()->count(),
            'snapshots' => SourceSnapshot::query()->count(),
            'draft_dossiers' => EditorialDossier::query()->where('status', 'draft')->count(),
        ];
    }
}
