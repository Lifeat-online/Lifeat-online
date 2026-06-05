<?php

namespace App\Support\Logging;

use DateTimeInterface;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class OperationalLog
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEY_PARTS = [
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

    public static function info(string $event, array $context = []): void
    {
        Log::info(self::message($event), self::context($event, $context));
    }

    public static function warning(string $event, array $context = []): void
    {
        Log::warning(self::message($event), self::context($event, $context));
    }

    public static function error(string $event, array $context = []): void
    {
        Log::error(self::message($event), self::context($event, $context));
    }

    public static function hashValue(?string $value): ?string
    {
        return filled($value) ? hash('sha256', $value) : null;
    }

    private static function message(string $event): string
    {
        return 'lifeat.operational.'.$event;
    }

    private static function context(string $event, array $context): array
    {
        return self::sanitize(array_merge([
            'event' => $event,
            'domain' => str_contains($event, '.') ? str($event)->before('.')->toString() : $event,
        ], $context));
    }

    private static function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $sanitized[$key] = self::isSensitiveKey((string) $key)
                ? self::REDACTED
                : self::normalizeValue($value);
        }

        return $sanitized;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::sanitize($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Model) {
            return [
                'model' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            return get_debug_type($value);
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = str($key)->lower()->replace(['-', ' '], '_')->toString();

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
