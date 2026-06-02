<?php

namespace App\Http\Controllers;

use App\Models\AskLifeFeedback;
use App\Services\AskLifeService;
use App\Services\VoiceGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AskLifeController extends Controller
{
    public function store(Request $request, AskLifeService $askLife): JsonResponse
    {
        $this->ensureDevOwner($request);

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
        ]);

        return response()->json($askLife->answer(
            $validated['question'],
            $request->user(),
            $validated['history'] ?? [],
            $validated['context'] ?? [],
        ));
    }

    public function feedback(Request $request): JsonResponse
    {
        $this->ensureDevOwner($request);

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

    public function speak(Request $request, VoiceGatewayService $voice): JsonResponse
    {
        $this->ensureDevOwner($request);

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

    private function ensureDevOwner(Request $request): void
    {
        abort_unless($request->user()?->isDevOwner(), 403, 'Jimmy is currently limited to the Dev owner.');
    }
}
