<?php

namespace App\Ai\Operator;

use App\Ai\Operator\Contracts\OperatorTaskPlanner;
use App\Models\OperatorTask;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class OperatorTaskOrchestrator
{
    public function __construct(
        private readonly OperatorTaskPlanner $planner,
        private readonly OperatorToolRegistry $registry,
        private readonly OperatorToolRuntime $runtime,
    ) {}

    public function run(string $taskId): void
    {
        $task = OperatorTask::query()->with('user')->findOrFail($taskId);
        if (in_array($task->status, [OperatorTask::STATUS_COMPLETED, OperatorTask::STATUS_CANCELLED, OperatorTask::STATUS_WAITING_FOR_APPROVAL, OperatorTask::STATUS_WAITING_FOR_INPUT], true)) {
            return;
        }

        $task->update([
            'status' => OperatorTask::STATUS_RUNNING,
            'started_at' => $task->started_at ?? now(),
            'error' => null,
        ]);

        try {
            while ($task->steps()->count() < $task->step_limit) {
                $task->refresh();
                if ($task->status === OperatorTask::STATUS_CANCELLED) {
                    return;
                }
                $this->enforceLimits($task);

                $tools = collect($this->registry->all())
                    ->filter(fn ($tool): bool => $tool->authorize($task->user))
                    ->reject(fn ($tool): bool => $tool->risk() === 'R4')
                    ->reject(fn ($tool): bool => $tool->risk() === 'R1' && ! config('ai_platform.operator.r1_auto_enabled', true))
                    ->map(fn ($tool): array => [
                        'name' => $tool->name(),
                        'risk' => $tool->risk(),
                        'arguments' => array_keys($tool->rules()),
                    ])->values()->all();
                $action = $this->planner->nextAction($task, $task->user, $tools);
                $this->recordUsage($task, (array) ($action['usage'] ?? []));
                if (isset($action['plan']) && is_array($action['plan'])) {
                    $task->update(['plan' => array_values($action['plan'])]);
                }

                if ($action['action'] === 'complete') {
                    $this->complete($task, (string) ($action['summary'] ?? 'Task completed.'));

                    return;
                }

                if ($action['action'] === 'ask_user') {
                    $this->waitForInput($task, (string) ($action['question'] ?? $action['summary'] ?? 'Developer input is required.'));

                    return;
                }

                $this->executeToolAction($task, $action);
                if (in_array($task->fresh()->status, [OperatorTask::STATUS_WAITING_FOR_APPROVAL, OperatorTask::STATUS_WAITING_FOR_INPUT], true)) {
                    return;
                }
            }

            throw new \RuntimeException('The operator task reached its configured step limit.');
        } catch (\Throwable $exception) {
            $task->refresh();
            if (! in_array($task->status, [OperatorTask::STATUS_CANCELLED, OperatorTask::STATUS_WAITING_FOR_APPROVAL, OperatorTask::STATUS_WAITING_FOR_INPUT], true)) {
                $task->update([
                    'status' => OperatorTask::STATUS_FAILED,
                    'error' => Str::limit($exception->getMessage(), 1000, ''),
                    'completed_at' => now(),
                ]);
                $this->message($task, 'Task failed: '.$task->error, ['status' => $task->status]);
            }
            throw $exception;
        }
    }

    private function executeToolAction(OperatorTask $task, array $action): void
    {
        $tool = $this->registry->get((string) $action['tool']);
        if (! $tool->authorize($task->user) || $tool->risk() === 'R4') {
            throw new AuthorizationException('The task selected an unauthorized operator tool.');
        }

        $step = $task->steps()->create([
            'position' => $task->steps()->count() + 1,
            'action' => 'tool',
            'tool' => $tool->name(),
            'risk' => $tool->risk(),
            'status' => 'running',
            'arguments' => $action['arguments'],
            'started_at' => now(),
        ]);

        if (in_array($tool->risk(), ['R2', 'R3'], true)) {
            $step->update(['status' => OperatorTask::STATUS_WAITING_FOR_APPROVAL]);
            $task->update(['status' => OperatorTask::STATUS_WAITING_FOR_APPROVAL]);
            $this->message($task, (string) ($action['summary'] ?? 'This step requires developer approval.'), [
                'task_step_id' => $step->id,
                'tool' => $tool->name(),
                'risk' => $tool->risk(),
                'status' => OperatorTask::STATUS_WAITING_FOR_APPROVAL,
            ]);

            return;
        }

        try {
            $result = $this->runtime->execute($task->user, $tool->name(), $action['arguments'], $task->id.':'.$step->position);
            $step->update([
                'status' => 'succeeded',
                'result' => $result['result'],
                'operator_tool_run_id' => $result['run_id'],
                'completed_at' => now(),
            ]);
            $this->recordSources($task, (array) ($result['result'] ?? []));
            $this->incrementSteps($task);
            $this->message($task, (string) ($action['summary'] ?? $tool->name().' completed.'), [
                'task_step_id' => $step->id,
                'tool' => $tool->name(),
                'risk' => $tool->risk(),
                'result' => $result['result'],
            ]);
            if ((bool) data_get($result, 'result.requires_input', false)) {
                $this->waitForInput($task, (string) data_get($result, 'result.question', 'Developer input is required before publication.'));
            }
        } catch (\Throwable $exception) {
            $step->update(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 1000, ''), 'completed_at' => now()]);
            throw $exception;
        }
    }

    private function complete(OperatorTask $task, string $summary): void
    {
        $task->update([
            'status' => OperatorTask::STATUS_COMPLETED,
            'result' => ['summary' => $summary],
            'completed_at' => now(),
        ]);
        $this->message($task, $summary, ['status' => OperatorTask::STATUS_COMPLETED]);
    }

    private function waitForInput(OperatorTask $task, string $question): void
    {
        $task->steps()->create([
            'position' => $task->steps()->count() + 1,
            'action' => 'ask_user',
            'status' => OperatorTask::STATUS_WAITING_FOR_INPUT,
            'result' => ['question' => $question],
            'completed_at' => now(),
        ]);
        $task->update(['status' => OperatorTask::STATUS_WAITING_FOR_INPUT]);
        $this->message($task, $question, ['status' => OperatorTask::STATUS_WAITING_FOR_INPUT]);
    }

    private function message(OperatorTask $task, string $content, array $payload): void
    {
        $task->conversation->messages()->create(['role' => 'assistant', 'content' => $content, 'payload' => $payload]);
        $task->conversation->update(['last_activity_at' => now()]);
    }

    private function recordUsage(OperatorTask $task, array $usage): void
    {
        $current = $task->usage ?? ['steps' => 0, 'cost' => 0];
        $current['cost'] = round((float) ($current['cost'] ?? 0) + (float) ($usage['cost'] ?? 0), 6);
        if (! empty($usage['generation_id'])) {
            $current['generation_ids'] = array_values(array_unique([...($current['generation_ids'] ?? []), $usage['generation_id']]));
        }
        $task->update(['usage' => $current]);
    }

    private function incrementSteps(OperatorTask $task): void
    {
        $usage = $task->fresh()->usage ?? [];
        $usage['steps'] = (int) ($usage['steps'] ?? 0) + 1;
        $task->update(['usage' => $usage]);
    }

    private function recordSources(OperatorTask $task, array $result): void
    {
        $ids = collect($task->sources ?? [])
            ->merge(isset($result['snapshot_id']) ? [(int) $result['snapshot_id']] : [])
            ->merge(array_map('intval', (array) ($result['source_snapshot_ids'] ?? [])))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
        if ($ids !== ($task->sources ?? [])) {
            $task->update(['sources' => $ids]);
        }
    }

    private function enforceLimits(OperatorTask $task): void
    {
        if ($task->started_at?->diffInSeconds(now()) > (int) config('ai_platform.operator.task_timeout_seconds', 300)) {
            throw new \RuntimeException('The operator task exceeded its time limit.');
        }
        if ((float) data_get($task->usage, 'cost', 0) > (float) config('ai_platform.operator.max_cost', 1.00)) {
            throw new \RuntimeException('The operator task exceeded its AI spend limit.');
        }
    }
}
