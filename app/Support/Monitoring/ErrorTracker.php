<?php

namespace App\Support\Monitoring;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorTracker
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEYS = [
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'credential',
        'merchant_key',
        'passphrase',
        'password',
        'private_key',
        'secret',
        'signature',
        'token',
    ];

    public function report(Throwable $exception, ?Request $request = null, array $context = []): void
    {
        if (! (bool) config('error_tracking.enabled', false) || ! $this->shouldReport($exception)) {
            return;
        }

        $payload = $this->payload($exception, $request, $context);

        match ((string) config('error_tracking.driver', 'log')) {
            'webhook' => $this->sendWebhook($payload),
            'null', 'none' => null,
            default => $this->writeLog($payload),
        };
    }

    private function shouldReport(Throwable $exception): bool
    {
        if ($exception instanceof HttpExceptionInterface
            && in_array($exception->getStatusCode(), (array) config('error_tracking.ignore_statuses', []), true)) {
            return false;
        }

        $sampleRate = max(0.0, min(1.0, (float) config('error_tracking.sample_rate', 1.0)));

        if ($sampleRate >= 1.0) {
            return true;
        }

        if ($sampleRate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() <= $sampleRate;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function payload(Throwable $exception, ?Request $request, array $context): array
    {
        $payload = [
            'event' => 'exception.reported',
            'fingerprint' => $this->fingerprint($exception),
            'reported_at' => now()->toIso8601String(),
            'environment' => (string) config('error_tracking.environment', app()->environment()),
            'release' => config('error_tracking.release'),
            'provider' => (string) config('error_tracking.provider', 'custom'),
            'exception' => [
                'class' => $exception::class,
                'message' => $this->redactString(Str::limit($exception->getMessage(), 1000, '...')),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
            'request' => $request ? $this->requestPayload($request) : null,
            'context' => $this->redact($context),
        ];

        if ((bool) config('error_tracking.include_trace', true)) {
            $payload['trace'] = collect($exception->getTrace())
                ->take(max(1, (int) config('error_tracking.trace_frames', 12)))
                ->map(fn (array $frame): array => [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'function' => $frame['function'] ?? null,
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(Request $request): array
    {
        return [
            'method' => $request->method(),
            'url' => $request->url(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'user_agent' => $this->redactString((string) $request->userAgent()),
            'request_id' => $request->headers->get('X-Request-ID') ?: $request->headers->get('X-Correlation-ID'),
            'user_id' => $request->user()?->id,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendWebhook(array $payload): void
    {
        $url = trim((string) config('error_tracking.webhook_url', ''));

        if ($url === '') {
            $this->writeLog(array_merge($payload, [
                'delivery_warning' => 'ERROR_TRACKING_WEBHOOK_URL is not configured.',
            ]));

            return;
        }

        try {
            Http::timeout(max(1, (int) config('error_tracking.timeout', 3)))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $deliveryException) {
            Log::warning('lifeat.error_tracker.delivery_failed', [
                'provider' => (string) config('error_tracking.provider', 'custom'),
                'driver' => 'webhook',
                'exception' => $deliveryException::class,
                'message' => $this->redactString($deliveryException->getMessage()),
                'fingerprint' => $payload['fingerprint'] ?? null,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeLog(array $payload): void
    {
        Log::channel((string) config('error_tracking.log_channel', config('logging.default')))
            ->error('lifeat.error_tracker.exception', $payload);
    }

    private function fingerprint(Throwable $exception): string
    {
        return hash('sha256', implode('|', [
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage(),
        ]));
    }

    private function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $key => $item) {
                $redacted[$key] = $this->isSensitiveKey((string) $key)
                    ? self::REDACTED
                    : $this->redact($item);
            }

            return $redacted;
        }

        if (is_string($value)) {
            return $this->redactString($value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private function redactString(string $value): string
    {
        return (string) preg_replace(
            '/(api[_-]?key|token|secret|password|signature|authorization|cookie)(\s*[:=]\s*)([^\s&]+)/i',
            '$1$2'.self::REDACTED,
            $value
        );
    }
}
