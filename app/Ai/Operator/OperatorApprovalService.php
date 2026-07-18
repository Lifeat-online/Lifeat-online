<?php

namespace App\Ai\Operator;

use App\Models\OperatorToolApproval;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

class OperatorApprovalService
{
    public function __construct(private readonly OperatorToolRegistry $registry) {}

    public function issue(User $user, string $toolName, array $arguments): array
    {
        if (! config('ai_platform.operator.enabled') || ! config('ai_platform.operator.mutations_enabled')) {
            throw new AuthorizationException('Operator mutations are disabled.');
        }
        $tool = $this->registry->get($toolName);
        if (! in_array($tool->risk(), ['R2', 'R3'], true) || ! $tool->authorize($user)) {
            throw new AuthorizationException('This tool cannot be approved by the current user.');
        }
        $validated = Validator::make($arguments, $tool->rules())->validate();
        $argumentsHash = $this->argumentsHash($validated);
        $version = $tool->recordVersion($validated);
        $approval = new OperatorToolApproval([
            'approved_by' => $user->id,
            'tool' => $tool->name(),
            'risk' => $tool->risk(),
            'arguments_hash' => $argumentsHash,
            'record_version' => $version,
            'expires_at' => now()->addMinutes(10),
        ]);
        $approval->id = (string) str()->uuid();
        $approval->signature = $this->signature($approval->id, $tool->name(), $argumentsHash, $version, $approval->expires_at->getTimestamp());
        $approval->save();

        return ['approval_token' => $approval->id.'.'.$approval->signature, 'expires_at' => $approval->expires_at->toIso8601String(), 'risk' => $approval->risk];
    }

    public function consume(string $token, string $toolName, array $arguments): OperatorToolApproval
    {
        [$id, $providedSignature] = array_pad(explode('.', $token, 2), 2, null);
        $approval = $id ? OperatorToolApproval::query()->lockForUpdate()->find($id) : null;
        if (! $approval || ! $providedSignature || $approval->used_at || $approval->expires_at->isPast() || $approval->tool !== $toolName) {
            throw new AuthorizationException('Operator approval is missing, expired, or already used.');
        }

        $tool = $this->registry->get($toolName);
        $argumentsHash = $this->argumentsHash($arguments);
        $version = $tool->recordVersion($arguments);
        $expected = $this->signature($approval->id, $toolName, $argumentsHash, $version, $approval->expires_at->getTimestamp());
        if ($argumentsHash !== $approval->arguments_hash || $version !== $approval->record_version || ! hash_equals($expected, $providedSignature)) {
            throw new AuthorizationException('Operator approval is stale or does not match these arguments.');
        }

        $approval->update(['used_at' => now()]);

        return $approval;
    }

    private function argumentsHash(array $arguments): string
    {
        $sort = function (array $values) use (&$sort): array {
            ksort($values);
            return array_map(fn ($value) => is_array($value) ? $sort($value) : $value, $values);
        };

        return hash('sha256', json_encode($sort($arguments), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function signature(string $id, string $tool, string $argumentsHash, string $version, int $expires): string
    {
        return hash_hmac('sha256', implode('|', [$id, $tool, $argumentsHash, $version, $expires]), (string) config('app.key'));
    }
}
