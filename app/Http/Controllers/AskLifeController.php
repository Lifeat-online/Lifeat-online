<?php

namespace App\Http\Controllers;

use App\Ai\PublicAssistant\PublicAssistantAccess;
use App\Ai\PublicAssistant\ChatSessionStore;
use App\Ai\PublicAssistant\PublicAssistantService;
use App\Models\AskLifeFeedback;
use App\Services\VoiceGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AskLifeController extends Controller
{
    public function store(Request $request, PublicAssistantService $askLife, PublicAssistantAccess $access, ChatSessionStore $chats): JsonResponse
    {
        $this->ensureAvailable($request, $access);

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:1000'],
            'context' => ['nullable', 'array'],
            'context.page_type' => ['nullable', 'string', 'max:80'],
            'context.page_title' => ['nullable', 'string', 'max:180'],
            'context.page_heading' => ['nullable', 'string', 'max:180'],
            'context.page_url' => ['nullable', 'string', 'max:2048'],
            'context.path' => ['nullable', 'string', 'max:500'],
            'context.timezone' => ['nullable', 'string', 'max:80'],
            'context.local_time' => ['nullable', 'string', 'max:80'],
            'context.locale' => ['nullable', 'string', 'max:20'],
            'session_id' => ['nullable', 'uuid'],
        ]);

        $locale = (string) data_get($validated, 'context.locale', app()->getLocale());
        $session = $chats->resolve($request, $validated['session_id'] ?? null, $locale);
        $history = $chats->history($session);
        $answer = $askLife->answer(
            $validated['question'],
            $request->user(),
            $history ?: ($validated['history'] ?? []),
            $validated['context'] ?? [],
        );
        $chats->record($session, $validated['question'], $answer);
        $answer['session_id'] = $session->id;

        return response()->json($answer);
    }

    public function feedback(Request $request, PublicAssistantAccess $access): JsonResponse
    {
        $this->ensureAvailable($request, $access);

        $validated = $request->validate([
            'rating' => ['required', 'string', Rule::in(['helpful', 'not_helpful'])],
            'question' => ['required', 'string', 'min:3', 'max:500'],
            'answer' => ['required', 'string', 'min:2', 'max:4000'],
            'generation_id' => ['nullable', 'integer', 'exists:ai_generations,id'],
            'intent' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:40'],
            'source_ids' => ['nullable', 'array', 'max:20'],
            'source_ids.*' => ['string', 'max:120'],
            'sources' => ['nullable', 'array', 'max:10'],
            'sources.*.id' => ['nullable', 'string', 'max:120'],
            'sources.*.type' => ['nullable', 'string', 'max:60'],
            'sources.*.title' => ['nullable', 'string', 'max:180'],
            'page_context' => ['nullable', 'array'],
            'page_context.page_type' => ['nullable', 'string', 'max:80'],
            'page_context.page_title' => ['nullable', 'string', 'max:180'],
            'page_context.page_heading' => ['nullable', 'string', 'max:180'],
            'page_context.page_url' => ['nullable', 'string', 'max:2048'],
            'page_context.path' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        AskLifeFeedback::create([
            'user_id' => $request->user()?->id,
            'ai_generation_id' => $validated['generation_id'] ?? null,
            'rating' => $validated['rating'],
            'intent' => $validated['intent'] ?? null,
            'source' => $validated['source'] ?? null,
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'source_ids' => $validated['source_ids'] ?? [],
            'sources' => $validated['sources'] ?? [],
            'page_context' => $validated['page_context'] ?? [],
            'reason' => $validated['reason'] ?? null,
            'ip_hash' => $request->ip()
                ? hash('sha256', $request->ip().'|'.(string) config('app.key'))
                : null,
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['ok' => true]);
    }

    public function speak(Request $request, VoiceGatewayService $voice, PublicAssistantAccess $access): JsonResponse
    {
        $this->ensureAvailable($request, $access);

        $validated = $request->validate([
            'text' => ['required', 'string', 'min:2', 'max:1000'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'af'])],
        ]);

        $result = $voice->speakAskLife(
            $validated['text'],
            $validated['locale'] ?? null,
            $request->user()
        );

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function destroySession(Request $request, string $session, PublicAssistantAccess $access, ChatSessionStore $chats): JsonResponse
    {
        $this->ensureAvailable($request, $access);
        abort_unless($chats->deleteOwned($request, $session), 404);

        return response()->json(['ok' => true]);
    }

    public function stream(Request $request, PublicAssistantService $askLife, PublicAssistantAccess $access, ChatSessionStore $chats): StreamedResponse
    {
        abort_unless(config('ai_platform.public_chat.streaming_enabled'), 404);
        $payload = $this->store($request, $askLife, $access, $chats)->getData(true);

        return response()->stream(function () use ($payload): void {
            foreach (str_split((string) ($payload['answer'] ?? ''), 120) as $chunk) {
                echo "event: delta\n".'data: '.json_encode(['text' => $chunk], JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
            echo "event: done\n".'data: '.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function ensureAvailable(Request $request, PublicAssistantAccess $access): void
    {
        abort_unless($access->allowed($request->user()), 403, 'Ask Life is not enabled for this rollout stage.');
    }
}
