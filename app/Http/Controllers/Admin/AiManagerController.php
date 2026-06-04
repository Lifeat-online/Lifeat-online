<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiManagerAction;
use App\Services\AuditLogService;
use App\Services\AutonomousAiManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiManagerController extends Controller
{
    public function index(Request $request, AutonomousAiManagerService $manager): View
    {
        return view('admin.ai-manager.index', $manager->dashboard($request->user()));
    }

    public function updatePolicy(Request $request, AutonomousAiManagerService $manager): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in([
                AiManagerAction::MODE_OBSERVER,
                AiManagerAction::MODE_APPROVAL,
                AiManagerAction::MODE_BUDGETED,
                AiManagerAction::MODE_AUTONOMOUS,
            ])],
            'article_fund_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'monthly_platform_ad_cap' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'max_actions_per_run' => ['required', 'integer', 'min:1', 'max:25'],
            'allow_public_publishing' => ['nullable', 'boolean'],
            'allow_direct_marketing' => ['nullable', 'boolean'],
            'allow_external_ad_spend' => ['nullable', 'boolean'],
            'require_human_payout_approval' => ['nullable', 'boolean'],
            'emergency_stop' => ['nullable', 'boolean'],
        ]);

        $manager->updatePolicy($request->user(), $validated + [
            'allow_public_publishing' => $request->boolean('allow_public_publishing'),
            'allow_direct_marketing' => $request->boolean('allow_direct_marketing'),
            'allow_external_ad_spend' => $request->boolean('allow_external_ad_spend'),
            'require_human_payout_approval' => $request->boolean('require_human_payout_approval'),
            'emergency_stop' => $request->boolean('emergency_stop'),
        ]);

        return redirect()->route('admin.ai-manager.index')->with('status', 'AI Manager policy saved.');
    }

    public function generateRecommendations(Request $request, AutonomousAiManagerService $manager): RedirectResponse
    {
        $result = $manager->generateRecommendations($request->user());

        return redirect()->route('admin.ai-manager.index')
            ->with('status', "AI Manager generated {$result['count']} proposed action(s).");
    }

    public function updateAction(
        Request $request,
        AiManagerAction $aiManagerAction,
        AutonomousAiManagerService $manager,
        AuditLogService $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AiManagerAction::STATUS_APPROVED,
                AiManagerAction::STATUS_DISMISSED,
                AiManagerAction::STATUS_BLOCKED,
                AiManagerAction::STATUS_EXECUTED,
            ])],
        ]);

        $before = $aiManagerAction->only(['status', 'reviewed_by', 'reviewed_at', 'executed_at']);
        $updated = $manager->transitionAction($aiManagerAction, $request->user(), $validated['status']);

        $audit->log($request, 'ai_manager_action.'.$validated['status'], $updated, $before, $updated->fresh()->only([
            'status',
            'reviewed_by',
            'reviewed_at',
            'executed_at',
        ]));

        return redirect()->route('admin.ai-manager.index')
            ->with('status', 'AI Manager action marked '.str_replace('_', ' ', $validated['status']).'.');
    }
}
