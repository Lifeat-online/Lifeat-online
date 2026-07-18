<?php

namespace App\Ai\Operator\Tools;

use App\Ai\Operator\Contracts\OperatorTool;
use App\Ai\Operator\Contracts\WebSearchProvider;
use App\Models\User;

class WebSearchTool implements OperatorTool
{
    public function __construct(private readonly WebSearchProvider $search) {}

    public function name(): string
    {
        return 'research.web_search';
    }

    public function risk(): string
    {
        return 'R0';
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:500'],
            'locale' => ['sometimes', 'string', 'max:20'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->hasRole('dev', 'developer');
    }

    public function recordVersion(array $arguments): string
    {
        return 'read-only';
    }

    public function execute(User $user, array $arguments): array
    {
        return [
            'query' => $arguments['query'],
            'results' => $this->search->search($arguments['query'], $arguments['locale'] ?? 'en-ZA', (int) ($arguments['limit'] ?? 8)),
        ];
    }
}
