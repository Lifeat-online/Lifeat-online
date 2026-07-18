<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Operator\OperatorApprovalService;
use App\Ai\Operator\OperatorToolRegistry;
use App\Ai\Operator\OperatorToolRuntime;
use App\Http\Controllers\Controller;
use App\Jobs\RunOperatorTask;
use App\Models\OperatorConversation;
use App\Models\OperatorTask;
use App\Models\OperatorToolRun;
use App\Models\SourceSnapshot;
use App\Services\AiGatewayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiOperatorController extends Controller
{
    public function storeTask(Request $request): JsonResponse
    {
        abort_unless(config('ai_platform.operator.enabled') && config('ai_platform.operator.agent_enabled'), 403, 'The enhanced Operator Assistant is disabled.');
        abort_unless($request->user()->hasRole('dev', 'developer'), 403);
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'uuid', 'exists:operator_conversations,id'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $task = DB::transaction(function () use ($request, $validated): OperatorTask {
            $conversation = isset($validated['conversation_id'])
                ? OperatorConversation::query()->where('user_id', $request->user()->id)->findOrFail($validated['conversation_id'])
                : OperatorConversation::create([
                    'user_id' => $request->user()->id,
                    'title' => Str::limit($validated['message'], 80, ''),
                    'last_activity_at' => now(),
                ]);
            $conversation->messages()->create(['role' => 'user', 'content' => $validated['message']]);
            $conversation->update(['last_activity_at' => now()]);

            return $conversation->tasks()->create([
                'user_id' => $request->user()->id,
                'goal' => $validated['message'],
                'status' => OperatorTask::STATUS_PLANNED,
                'step_limit' => max(1, (int) config('ai_platform.operator.step_limit', 12)),
                'usage' => ['steps' => 0, 'cost' => 0],
            ]);
        });

        RunOperatorTask::dispatch($task->id);

        return response()->json([
            'task_id' => $task->id,
            'conversation_id' => $task->operator_conversation_id,
            'status' => $task->status,
            'status_url' => route('admin.ai-operator.tasks.show', $task),
        ], 202);
    }

    public function showTask(Request $request, OperatorTask $operatorTask): JsonResponse
    {
        abort_unless($operatorTask->user_id === $request->user()->id, 404);
        $operatorTask->load('steps');

        return response()->json([
            'id' => $operatorTask->id,
            'conversation_id' => $operatorTask->operator_conversation_id,
            'goal' => $operatorTask->goal,
            'status' => $operatorTask->status,
            'plan' => $operatorTask->plan,
            'sources' => $this->sourceCards($operatorTask->sources ?? []),
            'usage' => $operatorTask->usage,
            'result' => $operatorTask->result,
            'error' => $operatorTask->error,
            'steps' => $operatorTask->steps,
        ]);
    }

    public function cancelTask(Request $request, OperatorTask $operatorTask): JsonResponse
    {
        abort_unless($operatorTask->user_id === $request->user()->id, 404);
        if (! in_array($operatorTask->status, [OperatorTask::STATUS_COMPLETED, OperatorTask::STATUS_FAILED, OperatorTask::STATUS_CANCELLED], true)) {
            $operatorTask->update(['status' => OperatorTask::STATUS_CANCELLED, 'cancelled_at' => now()]);
        }

        return response()->json(['id' => $operatorTask->id, 'status' => $operatorTask->fresh()->status]);
    }

    public function approveTask(
        Request $request,
        OperatorTask $operatorTask,
        OperatorApprovalService $approvals,
        OperatorToolRuntime $runtime,
    ): JsonResponse {
        abort_unless($operatorTask->user_id === $request->user()->id, 404);
        abort_unless($operatorTask->status === OperatorTask::STATUS_WAITING_FOR_APPROVAL, 409, 'This task is not waiting for approval.');
        $validated = $request->validate(['step_id' => ['required', 'integer']]);
        $step = $operatorTask->steps()
            ->whereKey($validated['step_id'])
            ->where('status', OperatorTask::STATUS_WAITING_FOR_APPROVAL)
            ->firstOrFail();
        abort_unless($step->tool && in_array($step->risk, ['R2', 'R3'], true), 409, 'This task step cannot be approved.');

        $approval = $approvals->issue($request->user(), $step->tool, $step->arguments ?? []);
        $result = $runtime->execute(
            $request->user(),
            $step->tool,
            $step->arguments ?? [],
            $operatorTask->id.':'.$step->position.':approved',
            $approval['approval_token'],
        );
        $run = OperatorToolRun::findOrFail($result['run_id']);
        $step->update([
            'status' => 'succeeded',
            'result' => $result['result'],
            'operator_tool_run_id' => $run->id,
            'operator_tool_approval_id' => $run->operator_tool_approval_id,
            'completed_at' => now(),
        ]);
        $this->incrementTaskStepUsage($operatorTask);
        $operatorTask->update(['status' => OperatorTask::STATUS_PLANNED]);
        $operatorTask->conversation->messages()->create([
            'role' => 'assistant',
            'tool' => $step->tool,
            'content' => 'Approved step completed: '.$step->tool,
            'payload' => ['task_step_id' => $step->id, 'result' => $result['result']],
        ]);
        RunOperatorTask::dispatch($operatorTask->id);

        return response()->json(['id' => $operatorTask->id, 'status' => $operatorTask->fresh()->status]);
    }

    public function rejectTask(Request $request, OperatorTask $operatorTask): JsonResponse
    {
        abort_unless($operatorTask->user_id === $request->user()->id, 404);
        abort_unless($operatorTask->status === OperatorTask::STATUS_WAITING_FOR_APPROVAL, 409, 'This task is not waiting for approval.');
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $operatorTask->steps()->where('status', OperatorTask::STATUS_WAITING_FOR_APPROVAL)->update([
            'status' => 'rejected',
            'error' => $validated['reason'],
            'completed_at' => now(),
        ]);
        $operatorTask->update([
            'status' => OperatorTask::STATUS_CANCELLED,
            'result' => ['summary' => 'Critical action rejected by the developer.', 'reason' => $validated['reason']],
            'cancelled_at' => now(),
        ]);
        $operatorTask->conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Task cancelled: '.$validated['reason'],
            'payload' => ['status' => OperatorTask::STATUS_CANCELLED],
        ]);

        return response()->json(['id' => $operatorTask->id, 'status' => $operatorTask->status]);
    }

    public function resumeTask(Request $request, OperatorTask $operatorTask): JsonResponse
    {
        abort_unless($operatorTask->user_id === $request->user()->id, 404);
        abort_unless($operatorTask->status === OperatorTask::STATUS_WAITING_FOR_INPUT, 409, 'This task is not waiting for input.');
        $validated = $request->validate(['message' => ['required', 'string', 'max:5000']]);
        $step = $operatorTask->steps()->where('status', OperatorTask::STATUS_WAITING_FOR_INPUT)->latest('position')->firstOrFail();
        $step->update([
            'status' => 'succeeded',
            'result' => [...($step->result ?? []), 'response' => $validated['message']],
            'completed_at' => now(),
        ]);
        $operatorTask->conversation->messages()->create(['role' => 'user', 'content' => $validated['message']]);
        $operatorTask->conversation->update(['last_activity_at' => now()]);
        $operatorTask->update(['status' => OperatorTask::STATUS_PLANNED]);
        RunOperatorTask::dispatch($operatorTask->id);

        return response()->json(['id' => $operatorTask->id, 'status' => $operatorTask->fresh()->status]);
    }

    private function incrementTaskStepUsage(OperatorTask $task): void
    {
        $usage = $task->usage ?? [];
        $usage['steps'] = (int) ($usage['steps'] ?? 0) + 1;
        $task->update(['usage' => $usage]);
    }

    private function sourceCards(array $ids): array
    {
        return SourceSnapshot::query()->whereIn('id', $ids)->get()->map(fn (SourceSnapshot $snapshot): array => [
            'id' => $snapshot->id,
            'url' => $snapshot->url,
            'host' => strtolower((string) parse_url($snapshot->url, PHP_URL_HOST)),
            'content_hash' => $snapshot->content_hash,
            'fetched_at' => $snapshot->fetched_at?->toIso8601String(),
            'fetch_error' => $snapshot->fetch_error,
        ])->values()->all();
    }

    public function jimmyChat(Request $request, AiGatewayService $ai): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array'],
        ]);

        $context = collect($validated['history'] ?? [])->map(fn ($turn) => ($turn['role'] ?? 'user').': '.($turn['content'] ?? ''))->implode("\n");

        $result = $ai->generateStructured('ask_life', 'jimmy_chat_v1', 'You are Jimmy, the Life@ editorial assistant. You help editors with article writing, research, source verification, and editorial tasks. Be helpful, concise, and accurate. Never invent facts.', [
            'context' => "Conversation:\n{$context}",
            'message' => $validated['message'],
            'schema' => ['answer' => 'Your helpful response to the editor.'],
        ]);

        return response()->json(['answer' => $result['ok'] ? ($result['payload']['answer'] ?? 'How can I help with editorial tasks?') : ($result['message'] ?? 'Jimmy is unavailable.')]);
    }

    public function index(Request $request, OperatorToolRegistry $registry): View
    {
        $conversations = OperatorConversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('last_activity_at')
            ->get();
        $conversation = $request->filled('conversation')
            ? $conversations->firstWhere('id', $request->string('conversation')->toString())
            : $conversations->first();
        $conversation?->load(['messages.run', 'tasks.steps']);
        $sourceSnapshots = SourceSnapshot::query()
            ->whereIn('id', collect($conversation?->tasks ?? [])->flatMap(fn (OperatorTask $task): array => $task->sources ?? [])->unique())
            ->get()
            ->keyBy('id');
        $agentEnabled = config('ai_platform.operator.enabled')
            && config('ai_platform.operator.agent_enabled')
            && $request->user()->hasRole('dev', 'developer');

        $labels = [
            'platform.health' => ['Platform health', 'Read current application and dependency health.'],
            'content.review_queue' => ['Content review queue', 'List articles that still require editorial action.'],
            'research.summary' => ['Research summary', 'Read research, snapshot, and dossier totals.'],
            'users.summary' => ['User summary', 'Read aggregate user and verification totals without personal records.'],
            'listings.review_queue' => ['Listing review queue', 'List business records awaiting operator review.'],
            'campaigns.summary' => ['Campaign summary', 'Read advertising and push campaign totals.'],
            'finance.summary' => ['Finance summary', 'Read payment and subscription status totals.'],
            'ai.operations_summary' => ['AI operations summary', 'Read generation failures and retrieval activity.'],
            'audits.recent' => ['Recent audits', 'Read the latest redacted audit event metadata.'],
            'content.propose_article_status' => ['Propose article status', 'Create a reviewable status-change proposal without mutating content.'],
            'content.apply_article_status' => ['Apply article status', 'Apply a separately approved article status change.'],
            'content.search' => ['Search content', 'Find existing articles and directory listings before changing content.'],
            'directory.find_duplicates' => ['Find listing duplicates', 'Check business name, city, website, and email matches.'],
            'directory.create_listing' => ['Create sourced listing', 'Create an unclaimed listing from retained public evidence.'],
            'directory.update_listing' => ['Update listing', 'Update safe listing fields without changing ownership.'],
            'research.web_search' => ['Search the web', 'Search current public sources through the configured grounded provider.'],
            'research.snapshot_source' => ['Capture source', 'Securely fetch and retain a selected public source.'],
            'research.compare_sources' => ['Compare sources', 'Review retained source excerpts and independent hosts.'],
            'research.build_dossier' => ['Build dossier', 'Build a claim and evidence dossier from a retained source.'],
            'editorial.create_event_article' => ['Create event article', 'Draft and publish an evidence-backed event article.'],
            'editorial.update_article' => ['Update article', 'Update safe article fields and publication status.'],
        ];

        $tools = collect($registry->all())
            ->filter(fn ($tool) => $tool->authorize($request->user()))
            ->filter(fn ($tool) => in_array($tool->risk(), ['R0', 'R1'], true))
            ->map(fn ($tool): array => [
                'name' => $tool->name(),
                'risk' => $tool->risk(),
                'label' => $labels[$tool->name()][0] ?? $tool->name(),
                'description' => $labels[$tool->name()][1] ?? '',
            ])->values();

        return view('admin.ai-operator.index', compact('conversations', 'conversation', 'tools', 'sourceSnapshots', 'agentEnabled'));
    }

    public function storeMessage(Request $request, OperatorToolRuntime $runtime): RedirectResponse
    {
        abort_unless(config('ai_platform.operator.enabled'), 403, 'The Operator Assistant is disabled.');
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'uuid', 'exists:operator_conversations,id'],
            'tool' => ['required', 'string', 'max:100'],
            'arguments' => ['nullable', 'string', 'max:10000'],
        ]);
        $conversation = isset($validated['conversation_id'])
            ? OperatorConversation::query()->where('user_id', $request->user()->id)->findOrFail($validated['conversation_id'])
            : OperatorConversation::create([
                'user_id' => $request->user()->id,
                'title' => 'Operator workspace '.now()->format('Y-m-d H:i'),
                'last_activity_at' => now(),
            ]);

        try {
            $arguments = json_decode($validated['arguments'] ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['arguments' => 'Arguments must be a JSON object.']);
        }
        if (! is_array($arguments) || ($arguments !== [] && array_is_list($arguments))) {
            throw ValidationException::withMessages(['arguments' => 'Arguments must be a JSON object.']);
        }

        $conversation->messages()->create([
            'role' => 'user',
            'tool' => $validated['tool'],
            'content' => 'Run '.$validated['tool'],
            'payload' => ['arguments' => $arguments],
        ]);

        $result = $runtime->execute($request->user(), $validated['tool'], $arguments, (string) Str::uuid());
        $conversation->messages()->create([
            'operator_tool_run_id' => $result['run_id'],
            'role' => 'assistant',
            'tool' => $validated['tool'],
            'content' => json_encode($result['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'payload' => $result,
        ]);
        $conversation->update(['last_activity_at' => now()]);

        return redirect()->route('admin.ai-operator.index', ['conversation' => $conversation->id]);
    }

    public function execute(Request $request, string $tool, OperatorToolRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'arguments' => ['present', 'array'],
            'idempotency_key' => ['required', 'string', 'max:200'],
            'approval_token' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json($runtime->execute($request->user(), $tool, $validated['arguments'], $validated['idempotency_key'], $validated['approval_token'] ?? null));
    }

    public function approve(Request $request, string $tool, OperatorApprovalService $approvals): JsonResponse
    {
        $validated = $request->validate(['arguments' => ['present', 'array']]);

        return response()->json($approvals->issue($request->user(), $tool, $validated['arguments']));
    }
}
