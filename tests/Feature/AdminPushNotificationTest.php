<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VapidKeySetupService;
use App\Services\WebPushDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_push_notification_section(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->mock(WebPushDeliveryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
        });

        $this->actingAs($admin)
            ->get(route('admin.push-notifications.test'))
            ->assertOk()
            ->assertSee('Push Notifications')
            ->assertSee('Send Push');
    }

    public function test_admin_can_send_push_notification_to_all_active_subscriptions(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->mock(WebPushDeliveryService::class, function (MockInterface $mock) use ($admin): void {
            $mock->shouldReceive('sendManual')
                ->once()
                ->withArgs(fn (User $user, array $payload, string $audience): bool => $user->is($admin)
                    && $payload['title'] === 'Backend push test'
                    && $payload['body'] === 'This is a test message.'
                    && $payload['url'] === route('admin.dashboard')
                    && $audience === 'all')
                ->andReturn([
                    'configured' => true,
                    'attempted' => 1,
                    'sent' => 1,
                    'failed' => 0,
                    'expired' => 0,
                ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.push-notifications.store'), [
                'audience' => 'all',
                'title' => 'Backend push test',
                'body' => 'This is a test message.',
                'url' => route('admin.dashboard'),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Push attempted for 1 subscription(s): 1 sent, 0 failed.');

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'push',
            'notification_type' => 'admin_manual_push_sent',
            'recipient' => 'all active browser subscriptions',
            'status' => 'sent',
        ]);
    }

    public function test_web_push_notice_about_optional_math_extensions_does_not_break_manual_send(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $keys = [
            'publicKey' => rtrim(strtr(base64_encode(str_repeat("\x04", 65)), '+/', '-_'), '='),
            'privateKey' => rtrim(strtr(base64_encode(str_repeat("\x01", 32)), '+/', '-_'), '='),
        ];

        $this->mock(VapidKeySetupService::class, function (MockInterface $mock) use ($keys): void {
            $mock->shouldReceive('publicKey')->andReturn($keys['publicKey']);
            $mock->shouldReceive('privateKey')->andReturn($keys['privateKey']);
            $mock->shouldReceive('subject')->andReturn('https://example.com');
        });

        $result = app(WebPushDeliveryService::class)->sendManual($admin, [
            'title' => 'Backend push test',
            'body' => 'This is a test message.',
            'url' => route('admin.dashboard'),
        ], 'self');

        $this->assertSame([
            'configured' => true,
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'expired' => 0,
        ], $result);
    }

    public function test_non_admin_roles_cannot_open_push_notification_test_section(): void
    {
        $user = User::factory()->create(['role' => 'registered_user']);

        $this->actingAs($user)
            ->get(route('admin.push-notifications.test'))
            ->assertForbidden();
    }
}
