<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Operator\OperatorApprovalService;
use App\Ai\Operator\OperatorToolRegistry;
use App\Ai\Operator\OperatorToolRuntime;
use App\Http\Controllers\Controller;
use App\Models\OperatorConversation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiOperatorController extends Controller
{
    public function index(Request $request, OperatorToolRegistry $registry): View
    {
        $conversations = OperatorConversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('last_activity_at')
            ->get();
        $conversation = $request->filled('conversation')
            ? $conversations->firstWhere('id', $request->string('conversation')->toString())
            : $conversations->first();
        $conversation?->load('messages.run');

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

        return view('admin.ai-operator.index', compact('conversations', 'conversation', 'tools'));
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
