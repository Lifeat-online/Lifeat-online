<?php

namespace App\Ai\PublicAssistant;

use App\Models\AiChatSession;
use Illuminate\Http\Request;

class ChatSessionStore
{
    public function resolve(Request $request, ?string $sessionId, string $locale): AiChatSession
    {
        $session = $sessionId ? $this->ownedQuery($request)->find($sessionId) : null;
        $retention = max(1, (int) config('ai_platform.public_chat.retention_days', 30));

        if (! $session) {
            $session = AiChatSession::create([
                'user_id' => $request->user()?->id,
                'locale' => $locale,
                'ip_hash' => $request->user() ? null : $this->ipHash($request),
                'last_activity_at' => now(),
                'expires_at' => now()->addDays($retention),
            ]);
        }

        return $session;
    }

    /** @return list<array{role:string, content:string}> */
    public function history(AiChatSession $session): array
    {
        return $session->messages()->latest('id')->limit(16)->get()->reverse()->map(fn ($message): array => [
            'role' => $message->role,
            'content' => $message->content,
        ])->values()->all();
    }

    public function record(AiChatSession $session, string $question, array $answer): void
    {
        $session->messages()->create(['role' => 'user', 'content' => $question]);
        $session->messages()->create([
            'role' => 'assistant',
            'content' => (string) ($answer['answer'] ?? ''),
            'sources' => $answer['sources'] ?? [],
            'ai_generation_id' => $answer['generation_id'] ?? null,
        ]);
        $session->update([
            'locale' => (string) ($answer['locale'] ?? $session->locale),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(max(1, (int) config('ai_platform.public_chat.retention_days', 30))),
        ]);
    }

    public function deleteOwned(Request $request, string $sessionId): bool
    {
        $session = $this->ownedQuery($request)->find($sessionId);

        return $session ? (bool) $session->delete() : false;
    }

    private function ownedQuery(Request $request)
    {
        return AiChatSession::query()->when(
            $request->user(),
            fn ($query) => $query->where('user_id', $request->user()->id),
            fn ($query) => $query->whereNull('user_id')->where('ip_hash', $this->ipHash($request)),
        );
    }

    private function ipHash(Request $request): ?string
    {
        return $request->ip() ? hash('sha256', $request->ip().'|'.(string) config('app.key')) : null;
    }
}
