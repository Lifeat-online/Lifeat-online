<?php

namespace Tests\Feature;

use App\Models\AiChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAssistantConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_is_persisted_and_owner_can_delete_it(): void
    {
        config(['services.ai.providers.openrouter.key' => '']);
        $user = User::factory()->create(['role' => 'dev']);

        $response = $this->actingAs($user)->postJson(route('ask-life.store'), [
            'question' => 'How do I add my business?',
        ])->assertOk();

        $sessionId = $response->json('session_id');
        $this->assertNotEmpty($sessionId);
        $this->assertDatabaseHas('ai_chat_sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        $this->assertSame(2, AiChatSession::find($sessionId)->messages()->count());

        $this->deleteJson(route('ask-life.sessions.destroy', $sessionId))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('ai_chat_sessions', ['id' => $sessionId]);
    }

    public function test_anonymous_session_cannot_be_deleted_from_another_ip(): void
    {
        config([
            'services.ai.providers.openrouter.key' => '',
            'ai_platform.public_chat.enabled' => true,
            'ai_platform.public_chat.anonymous_enabled' => true,
        ]);
        \App\Models\Setting::insert([
            ['key' => 'ai_public_chat.anonymous_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'ai_platform', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $sessionId = $this->withServerVariables(['REMOTE_ADDR' => '196.1.1.10'])
            ->postJson(route('ask-life.store'), ['question' => 'What events are public?'])
            ->assertOk()
            ->json('session_id');

        $this->withServerVariables(['REMOTE_ADDR' => '196.1.1.11'])
            ->deleteJson(route('ask-life.sessions.destroy', $sessionId))
            ->assertNotFound();

        $this->assertDatabaseHas('ai_chat_sessions', ['id' => $sessionId]);
    }

    public function test_retention_command_deletes_expired_conversations(): void
    {
        $session = AiChatSession::create([
            'locale' => 'en',
            'ip_hash' => hash('sha256', 'expired'),
            'last_activity_at' => now()->subDays(31),
            'expires_at' => now()->subMinute(),
        ]);
        $session->messages()->create(['role' => 'user', 'content' => 'Old private conversation']);

        $this->artisan('life:ai-chat:prune')->assertSuccessful();

        $this->assertDatabaseMissing('ai_chat_sessions', ['id' => $session->id]);
        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_streaming_endpoint_returns_sse_deltas_and_final_payload(): void
    {
        config([
            'services.ai.providers.openrouter.key' => '',
            'ai_platform.public_chat.streaming_enabled' => true,
        ]);
        $dev = User::factory()->create(['role' => 'dev']);

        $response = $this->actingAs($dev)->postJson(route('ask-life.stream'), [
            'question' => 'How do I add my business?',
        ])->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('event: delta', $content);
        $this->assertStringContainsString('event: done', $content);
        $this->assertStringContainsString('session_id', $content);
    }
}
