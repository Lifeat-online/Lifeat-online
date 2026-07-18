<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PublicAssistantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollout_requires_environment_and_database_gates(): void
    {
        config([
            'services.ai.providers.openrouter.key' => '',
            'ai_platform.public_chat.enabled' => true,
            'ai_platform.public_chat.anonymous_enabled' => true,
        ]);

        $user = User::factory()->create(['role' => 'registered_user']);
        $payload = ['question' => 'How do I add my business?'];

        $this->actingAs($user)->postJson(route('ask-life.store'), $payload)->assertForbidden();

        Setting::create([
            'key' => 'ai_public_chat.authenticated_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'ai_platform',
        ]);

        $this->actingAs($user)->postJson(route('ask-life.store'), $payload)->assertOk();
        Auth::logout();
        $this->postJson(route('ask-life.store'), $payload)->assertForbidden();

        Setting::create([
            'key' => 'ai_public_chat.anonymous_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'ai_platform',
        ]);

        $this->postJson(route('ask-life.store'), $payload)->assertOk();
    }
}
