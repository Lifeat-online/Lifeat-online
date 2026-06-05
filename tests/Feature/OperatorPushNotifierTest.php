<?php

namespace Tests\Feature;

use App\Models\BrowserPushSubscription;
use App\Models\OperatorAlertState;
use App\Models\User;
use App\Services\OperatorPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperatorPushNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ops.enabled', true);
        Config::set('ops.dev_is_admin', true);
        Config::set('ops.explicit_user_ids', []);
    }

    public function test_recipients_include_every_dev_user(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'registered_user']);
        $this->subscribe($dev);
        $this->subscribe($admin);
        $this->subscribe($member);

        $recipients = app(OperatorPushNotifier::class)->recipientsFor('user:registered');

        $this->assertTrue($recipients->contains('id', $dev->id), 'dev must receive business alerts');
        $this->assertTrue($recipients->contains('id', $admin->id), 'admin must receive business alerts');
        $this->assertFalse($recipients->contains('id', $member->id), 'member must not receive admin alerts');
    }

    public function test_recipients_for_operational_target_includes_support(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $this->subscribe($support);

        $recipients = app(OperatorPushNotifier::class)->recipientsFor('disk:critical');

        $this->assertTrue($recipients->contains('id', $support->id));
    }

    public function test_users_without_push_subscriptions_are_excluded(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);

        $recipients = app(OperatorPushNotifier::class)->recipientsFor('user:registered');

        $this->assertFalse($recipients->contains('id', $dev->id));
    }

    public function test_explicit_user_ids_roster_is_always_included(): void
    {
        $member = User::factory()->create(['role' => 'registered_user']);
        $this->subscribe($member);
        Config::set('ops.explicit_user_ids', [$member->id]);

        $recipients = app(OperatorPushNotifier::class)->recipientsFor('user:registered');

        $this->assertTrue($recipients->contains('id', $member->id));
    }

    public function test_send_is_a_noop_when_ops_disabled(): void
    {
        Config::set('ops.enabled', false);
        $dev = User::factory()->create(['role' => 'dev']);
        $this->subscribe($dev);

        $result = app(OperatorPushNotifier::class)->send(
            target: 'user:registered',
            title: 't',
            body: 'b',
        );

        $this->assertSame(0, $result['attempted']);
        $this->assertSame(0, OperatorAlertState::count());
    }

    public function test_send_is_a_noop_for_unknown_target(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->subscribe($dev);

        $result = app(OperatorPushNotifier::class)->send(
            target: 'totally:unknown',
            title: 't',
            body: 'b',
        );

        $this->assertSame(0, $result['attempted']);
    }

    public function test_acknowledge_marks_state_as_acknowledged(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->subscribe($dev);

        $notifier = app(OperatorPushNotifier::class);
        $notifier->send(target: 'disk:warning', title: 't', body: 'b');

        $state = OperatorAlertState::first();
        $this->assertNotNull($state);
        $this->assertNull($state->acknowledged_at);

        $this->assertTrue($notifier->acknowledge($dev->id, $state->fingerprint));
        $this->assertNotNull($state->fresh()->acknowledged_at);
    }

    public function test_critical_alert_is_resent_after_retry_window_but_not_before(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->subscribe($dev);
        Config::set('ops.retry_after_minutes', 60);

        $notifier = app(OperatorPushNotifier::class);
        $notifier->send(target: 'disk:critical', title: 't', body: 'b', severity: 'critical');
        $state = OperatorAlertState::first();
        $this->assertSame(1, $state->retries_sent);

        // Immediately retry - must be deduplicated.
        $notifier->send(target: 'disk:critical', title: 't', body: 'b', severity: 'critical');
        $this->assertSame(1, $state->fresh()->retries_sent);

        // Force the retry window to expire.
        $state->forceFill(['last_sent_at' => now()->subHours(2)])->save();
        $notifier->send(target: 'disk:critical', title: 't', body: 'b', severity: 'critical');
        $this->assertSame(2, $state->fresh()->retries_sent);
    }

    public function test_non_critical_alert_does_not_retry(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->subscribe($dev);

        $notifier = app(OperatorPushNotifier::class);
        $notifier->send(target: 'user:registered', title: 't', body: 'b', severity: 'info');
        $notifier->send(target: 'user:registered', title: 't', body: 'b', severity: 'info');
        $notifier->send(target: 'user:registered', title: 't', body: 'b', severity: 'info');

        $this->assertSame(1, OperatorAlertState::count());
        $this->assertSame(1, OperatorAlertState::first()->retries_sent);
    }

    public function test_fingerprint_changes_when_payload_differs(): void
    {
        $dev = User::factory()->create(['role' => 'dev']);
        $this->subscribe($dev);

        $notifier = app(OperatorPushNotifier::class);
        $notifier->send(target: 'user:registered', title: 'first', body: 'b');
        $notifier->send(target: 'user:registered', title: 'second', body: 'b');

        $this->assertSame(2, OperatorAlertState::count());
    }

    private function subscribe(User $user): BrowserPushSubscription
    {
        return BrowserPushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/'.Str::random(12),
            'endpoint_hash' => BrowserPushSubscription::endpointHash('https://push.example.test/'.Str::random(12)),
            'public_key' => Str::random(32),
            'auth_token' => Str::random(32),
            'content_encoding' => 'aesgcm',
        ]);
    }
}
