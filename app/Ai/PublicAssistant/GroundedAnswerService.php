<?php

namespace App\Ai\PublicAssistant;

use App\Models\User;
use App\Services\AiGatewayService;
use App\Support\Ai\AiPromptCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class GroundedAnswerService
{
    public function __construct(
        private readonly AiGatewayService $gateway,
        private readonly AiPromptCatalog $prompts,
        private readonly QueryUnderstandingService $queryUnderstanding,
    ) {}

    public function generate(
        string $question,
        Collection $sources,
        ?User $user,
        array $history,
        array $context,
        array $intent,
        array $search,
        string $locale,
    ): array {
        $prompt = $this->prompts->get('ask_life');

        try {
            $result = $this->gateway->generateStructured(
                'ask_life',
                $prompt['version'],
                $prompt['system'],
                [
                    'question' => $question,
                    'sources' => $sources->values()->all(),
                    'schema' => $prompt['schema'],
                    'conversation_history' => $this->formatHistory($history),
                    'detected_intent' => $intent,
                    'search_context' => $this->queryUnderstanding->publicSearchContext($search),
                    'page_context' => $context,
                    'target_locale' => $locale,
                    'target_language' => $this->queryUnderstanding->localeName($locale),
                    'language_instruction' => $this->queryUnderstanding->languageInstruction($locale),
                    'current_date' => CarbonImmutable::now($search['timezone'])->toDateString(),
                ],
                null,
                $user,
                $locale,
            );
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        if (! ($result['ok'] ?? false) || ! filled(data_get($result, 'payload.answer'))) {
            return ['ok' => false, 'message' => $result['message'] ?? 'AI provider did not return a usable answer.'];
        }

        $usedIds = collect(data_get($result, 'payload.source_ids', []))
            ->filter(fn ($id): bool => is_string($id) && $id !== '')
            ->values();
        if ($usedIds->isEmpty() || $usedIds->diff($sources->pluck('id'))->isNotEmpty()) {
            return ['ok' => false, 'message' => 'The generated answer did not provide valid supporting Life@ sources.'];
        }

        return [
            'ok' => true,
            'answer' => (string) data_get($result, 'payload.answer'),
            'confidence' => (float) data_get($result, 'payload.confidence', 0.65),
            'source_ids' => $usedIds->all(),
            'follow_up_questions' => collect(data_get($result, 'payload.follow_up_questions', []))->take(3)->values()->all(),
            'generation_id' => data_get($result, 'generation.id'),
        ];
    }

    private function formatHistory(array $history): array
    {
        return collect($history)
            ->filter(fn (array $turn): bool => isset($turn['role'], $turn['content']) && filled($turn['content']))
            ->take(16)
            ->map(fn (array $turn): array => [
                'role' => $turn['role'] === 'user' ? 'user' : 'assistant',
                'content' => Str::limit(trim((string) $turn['content']), 500),
            ])->values()->all();
    }
}
