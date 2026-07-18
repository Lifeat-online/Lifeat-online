<?php

namespace App\Services;

use App\Ai\PublicAssistant\AskLifeEngine;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Backward-compatible entry point for callers not yet migrated to PublicAssistantService.
 */
class AskLifeService
{
    public function __construct(private readonly AskLifeEngine $engine) {}

    public function answer(string $question, ?User $user = null, array $history = [], array $context = []): array
    {
        return $this->engine->answer($question, $user, $history, $context);
    }

    public function sourcesForQuestion(string $question, ?User $user = null, array $context = [], ?array $intent = null, ?array $search = null): Collection
    {
        return $this->engine->sourcesForQuestion($question, $user, $context, $intent, $search);
    }
}
