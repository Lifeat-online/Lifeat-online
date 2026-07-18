<?php

namespace App\Ai\PublicAssistant;

use App\Models\User;

class PublicAssistantService
{
    public function __construct(private readonly AskLifeEngine $engine) {}

    public function answer(string $question, ?User $user = null, array $history = [], array $context = []): array
    {
        return $this->engine->answer($question, $user, $history, $context);
    }
}
