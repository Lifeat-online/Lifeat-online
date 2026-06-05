<?php

namespace Tests\Feature;

use App\Support\Monitoring\ErrorTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorTrackingTest extends TestCase
{
    public function test_webhook_error_tracking_sends_redacted_exception_payload(): void
    {
        config([
            'error_tracking.enabled' => true,
            'error_tracking.driver' => 'webhook',
            'error_tracking.provider' => 'sentry',
            'error_tracking.webhook_url' => 'https://errors.example.test/report',
            'error_tracking.environment' => 'testing',
            'error_tracking.release' => 'test-release',
            'error_tracking.include_trace' => true,
            'error_tracking.trace_frames' => 3,
        ]);

        Http::fake([
            'https://errors.example.test/report' => Http::response(['ok' => true], 202),
        ]);

        $request = Request::create(
            '/checkout?token=query-secret',
            'POST',
            ['password' => 'request-secret'],
            server: [
                'HTTP_X_REQUEST_ID' => 'req-123',
                'HTTP_USER_AGENT' => 'LifeTest token=browser-secret',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );

        app(ErrorTracker::class)->report(
            new RuntimeException('Checkout failed password=inline-secret'),
            $request,
            [
                'order_id' => 123,
                'api_key' => 'sk-secret',
                'nested' => [
                    'signature' => 'raw-signature',
                    'safe' => 'visible',
                ],
            ]
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://errors.example.test/report'
                && $request['provider'] === 'sentry'
                && $request['environment'] === 'testing'
                && $request['release'] === 'test-release'
                && $request['exception']['class'] === RuntimeException::class
                && $request['exception']['message'] === 'Checkout failed password=[redacted]'
                && $request['request']['url'] === 'http://localhost/checkout'
                && $request['request']['request_id'] === 'req-123'
                && $request['request']['user_agent'] === 'LifeTest token=[redacted]'
                && $request['context']['order_id'] === 123
                && $request['context']['api_key'] === '[redacted]'
                && $request['context']['nested']['signature'] === '[redacted]'
                && $request['context']['nested']['safe'] === 'visible'
                && isset($request['fingerprint'], $request['trace'])
                && ! str_contains(json_encode($request->data()), 'request-secret')
                && ! str_contains(json_encode($request->data()), 'query-secret');
        });
    }

    public function test_error_tracking_ignores_configured_http_statuses(): void
    {
        config([
            'error_tracking.enabled' => true,
            'error_tracking.driver' => 'webhook',
            'error_tracking.webhook_url' => 'https://errors.example.test/report',
            'error_tracking.ignore_statuses' => [404],
        ]);

        Http::fake();

        app(ErrorTracker::class)->report(
            new NotFoundHttpException('Missing page token=hidden'),
            Request::create('/missing', 'GET')
        );

        Http::assertSentCount(0);
    }
}
