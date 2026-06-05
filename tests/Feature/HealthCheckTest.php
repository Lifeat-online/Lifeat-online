<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_monitoring_health_endpoint_reports_core_checks(): void
    {
        $response = $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checked_at',
                'environment',
                'checks' => [
                    'database' => ['status', 'message', 'meta'],
                    'storage' => ['status', 'message', 'meta'],
                    'disk' => ['status', 'message', 'meta'],
                    'queue' => ['status', 'message', 'meta'],
                    'payments' => ['status', 'message', 'meta'],
                    'mail' => ['status', 'message', 'meta'],
                ],
            ]);

        $this->assertContains($response->json('status'), ['ok', 'degraded']);
        $response
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.storage.status', 'ok')
            ->assertJsonPath('checks.queue.status', 'ok')
            ->assertJsonPath('checks.payments.status', 'ok')
            ->assertJsonPath('checks.mail.status', 'ok');
    }

    public function test_monitoring_health_endpoint_surfaces_failed_operations(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-HEALTH-001',
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 100,
            'vat_amount' => 15,
            'total' => 115,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'provider' => 'payfast',
            'status' => 'failed',
            'amount' => 115,
            'currency' => 'ZAR',
            'failure_reason' => 'Health check fixture',
        ]);

        NotificationLog::create([
            'channel' => 'email',
            'notification_type' => 'health_fixture',
            'recipient' => 'ops@example.com',
            'status' => 'failed',
            'sent_at' => now(),
        ]);

        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.payments.status', 'warning')
            ->assertJsonPath('checks.payments.meta.failed_last_24h', 1)
            ->assertJsonPath('checks.mail.status', 'warning')
            ->assertJsonPath('checks.mail.meta.failed_last_24h', 1);
    }

    public function test_monitoring_health_command_can_fail_on_warnings(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'health-failed-job',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Health check fixture',
            'failed_at' => now(),
        ]);

        $this->artisan('monitoring:health --fail-on-warning')
            ->expectsOutputToContain('Health status: degraded')
            ->expectsOutputToContain('[queue] Recent failed queue jobs need review.')
            ->assertExitCode(1);
    }
}
