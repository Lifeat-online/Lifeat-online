<?php

namespace App\Ai\Operator;

use App\Models\AuditLog;
use App\Models\OperatorToolRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OperatorToolRuntime
{
    public function __construct(private readonly OperatorToolRegistry $registry, private readonly OperatorApprovalService $approvals) {}

    public function execute(User $user, string $name, array $arguments, string $idempotencyKey, ?string $approvalToken = null): array
    {
        if (! config('ai_platform.operator.enabled')) {
            throw new AuthorizationException('The Operator Assistant is disabled.');
        }
        $tool = $this->registry->get($name);
        if (! $tool->authorize($user)) {
            throw new AuthorizationException('You are not authorized to use this operator tool.');
        }
        $validated = Validator::make($arguments, $tool->rules())->validate();
        $key = hash('sha256', $user->id.'|'.$idempotencyKey);
        $existing = OperatorToolRun::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            return ['ok' => $existing->status === 'succeeded', 'risk' => $existing->risk, 'result' => $existing->result, 'run_id' => $existing->id, 'cached' => true];
        }

        $safeArguments = $this->redact($validated);
        $run = OperatorToolRun::create([
            'user_id' => $user->id, 'tool' => $tool->name(), 'risk' => $tool->risk(),
            'arguments' => $safeArguments, 'status' => 'running', 'idempotency_key' => $key,
        ]);

        try {
            return DB::transaction(function () use ($user, $tool, $validated, $approvalToken, $safeArguments, $run): array {
                if (in_array($tool->risk(), ['R2', 'R3'], true)) {
                    if (! config('ai_platform.operator.mutations_enabled') || ! $approvalToken) {
                        throw new AuthorizationException('This operator tool requires an enabled mutation gate and signed approval.');
                    }
                    $approval = $this->approvals->consume($approvalToken, $tool->name(), $validated);
                    $run->update(['operator_tool_approval_id' => $approval->id]);
                }

                $result = $tool->execute($user, $validated);
                $run->update(['status' => 'succeeded', 'result' => $this->redact($result)]);
                $this->audit($user, $tool->name(), $tool->risk(), $run, $safeArguments, 'succeeded');

                return ['ok' => true, 'risk' => $tool->risk(), 'result' => $run->result, 'run_id' => $run->id, 'cached' => false];
            });
        } catch (\Throwable $exception) {
            $run->update(['status' => 'failed', 'error' => str($exception->getMessage())->limit(1000, '')]);
            $this->audit($user, $tool->name(), $tool->risk(), $run, $safeArguments, 'failed');
            throw $exception;
        }
    }

    private function audit(User $user, string $tool, string $risk, OperatorToolRun $run, array $arguments, string $status): void
    {
        AuditLog::create([
            'actor_user_id' => $user->id,
            'action' => 'ai_operator.tool.'.$tool,
            'subject_type' => OperatorToolRun::class,
            'subject_id' => null,
            'before_json' => ['arguments' => $arguments, 'risk' => $risk],
            'after_json' => ['run_id' => $run->id, 'result' => $run->result, 'status' => $status, 'error' => $run->error],
        ]);
    }

    private function redact(array $data): array
    {
        return collect($data)->mapWithKeys(function ($value, $key): array {
            if (preg_match('/password|secret|token|api.?key/i', (string) $key)) {
                return [$key => '[REDACTED]'];
            }
            return [$key => is_array($value) ? $this->redact($value) : $value];
        })->all();
    }
}
