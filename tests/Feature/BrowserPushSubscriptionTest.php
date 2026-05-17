<?php

namespace Tests\Feature;

use App\Models\BrowserPushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_revoke_browser_push_subscription(): void
    {
        $payload = $this->subscriptionPayload('https://updates.push.example/subscriptions/guest');

        $this->postJson(route('api.push-subscriptions.store'), $payload)
            ->assertOk()
            ->assertJson(['status' => 'subscribed']);

        $this->assertDatabaseHas('browser_push_subscriptions', [
            'endpoint_hash' => BrowserPushSubscription::endpointHash($payload['endpoint']),
            'user_id' => null,
            'revoked_at' => null,
        ]);

        $this->deleteJson(route('api.push-subscriptions.destroy'), [
            'endpoint' => $payload['endpoint'],
        ])->assertOk()->assertJson(['status' => 'unsubscribed']);

        $this->assertNotNull(BrowserPushSubscription::first()->revoked_at);
    }

    public function test_authenticated_subscription_is_linked_to_user_and_updates_existing_endpoint(): void
    {
        $user = User::factory()->create();
        $payload = $this->subscriptionPayload('https://updates.push.example/subscriptions/member');

        $this->actingAs($user)
            ->postJson(route('api.push-subscriptions.store'), $payload)
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('api.push-subscriptions.store'), [
                ...$payload,
                'keys' => [
                    'p256dh' => 'updated-public-key',
                    'auth' => 'updated-auth-token',
                ],
            ])
            ->assertOk();

        $this->assertSame(1, BrowserPushSubscription::count());
        $this->assertDatabaseHas('browser_push_subscriptions', [
            'endpoint_hash' => BrowserPushSubscription::endpointHash($payload['endpoint']),
            'user_id' => $user->id,
            'public_key' => 'updated-public-key',
            'auth_token' => 'updated-auth-token',
        ]);
    }

    private function subscriptionPayload(string $endpoint): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
            'content_encoding' => 'aes128gcm',
        ];
    }
}
