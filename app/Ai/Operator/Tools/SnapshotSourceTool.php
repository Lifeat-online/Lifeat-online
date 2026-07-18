<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Models\User;
use App\Services\Research\OnDemandResearchService;
use Illuminate\Support\Str;

class SnapshotSourceTool implements OperatorTool
{
    public function __construct(private readonly OnDemandResearchService $research) {}

    public function name(): string
    {
        return 'research.snapshot_source';
    }

    public function risk(): string
    {
        return 'R1';
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url:https', 'max:2000'],
            'title' => ['required', 'string', 'max:255'],
            'snippet' => ['nullable', 'string', 'max:2000'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'locale' => ['sometimes', 'string', 'max:20'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return hash('sha256', (string) $arguments['url']);
    }

    public function execute(User $user, array $arguments): array
    {
        $snapshot = $this->research->snapshot($arguments);

        return [
            'snapshot_id' => $snapshot->id,
            'research_item_id' => $snapshot->research_item_id,
            'url' => $snapshot->url,
            'content_hash' => $snapshot->content_hash,
            'fetch_error' => $snapshot->fetch_error,
            'untrusted_excerpt' => Str::limit($snapshot->content, 2000, ''),
        ];
    }
}
