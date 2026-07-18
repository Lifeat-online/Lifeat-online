<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Editorial\DossierBuilder;
use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\ResearchItem;
use App\Models\User;

class BuildDossierTool implements OperatorTool
{
    public function __construct(private readonly DossierBuilder $builder) {}

    public function name(): string
    {
        return 'research.build_dossier';
    }

    public function risk(): string
    {
        return 'R1';
    }

    public function rules(): array
    {
        return [
            'research_item_id' => ['required', 'integer', 'exists:research_items,id'],
            'approve' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return hash('sha256', json_encode(ResearchItem::findOrFail($arguments['research_item_id'])->getAttributes(), JSON_UNESCAPED_SLASHES));
    }

    public function execute(User $user, array $arguments): array
    {
        $dossier = $this->builder->build(ResearchItem::findOrFail($arguments['research_item_id']));
        if ($arguments['approve'] ?? false) {
            $dossier->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        }

        return ['dossier_id' => $dossier->id, 'status' => $dossier->fresh()->status, 'ready_for_writing' => $dossier->fresh()->readyForWriting()];
    }
}
