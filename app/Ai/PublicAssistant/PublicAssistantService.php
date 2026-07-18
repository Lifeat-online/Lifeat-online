<?php

namespace App\Ai\PublicAssistant;

use App\Models\User;
use App\Services\AskLifeService;

class PublicAssistantService
{
    public function __construct(private readonly AskLifeService $compatibilityEngine) {}

    public function answer(string $question, ?User $user = null, array $history = [], array $context = []): array
    {
        return $this->compatibilityEngine->answer($question, $user, $history, $context);
    }
}
