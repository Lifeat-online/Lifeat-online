<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AiGeneration;
use App\Models\AiManagerAction;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\ArticleWordLedger;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PushCampaign;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AutonomousAiManagerService
{
    private const REVENUE_PACKAGE_TYPES = [
        'business_directory',
        'event_package',
        'advert_package',
        'push_campaign',
    ];

    public function __construct(
        private readonly AiBudgetService $aiBudget,
        private readonly AiCostEstimatorService $costs,
    ) {
    }

    public function dashboard(?User $user = null): array
    {
        $policy = $this->policy();
        $kpis = $this->kpis();
        $allocation = $this->revenueAllocation($policy);

        return [
            'policy' => $policy,
            'kpis' => $kpis,
            'allocation' => $allocation,
            'brief' => $this->operatingBrief($policy, $kpis, $allocation),
            'domains' => $this->domainSummary(),
            'actions' => AiManagerAction::query()
                ->with(['reviewer', 'source'])
                ->latest()
                ->paginate(20),
            'openActionCount' => AiManagerAction::query()
                ->whereIn('status', [AiManagerAction::STATUS_PROPOSED, AiManagerAction::STATUS_APPROVED])
                ->count(),
        ];
    }

    public function policy(): array
    {
        $articlePercent = $this->numberSetting('ai_manager.article_fund_percent', 30, 0, 100);

        return [
            'mode' => $this->modeSetting(),
            'article_fund_percent' => $articlePercent,
            'owner_share_percent' => max(0, 100 - $articlePercent),
            'monthly_platform_ad_cap' => $this->numberSetting('ai_manager.monthly_platform_ad_cap', 0, 0, 1000000),
            'max_actions_per_run' => (int) $this->numberSetting('ai_manager.max_actions_per_run', 6, 1, 25),
            'allow_public_publishing' => $this->boolSetting('ai_manager.allow_public_publishing', false),
            'allow_direct_marketing' => $this->boolSetting('ai_manager.allow_direct_marketing', false),
            'allow_external_ad_spend' => $this->boolSetting('ai_manager.allow_external_ad_spend', false),
            'require_human_payout_approval' => $this->boolSetting('ai_manager.require_human_payout_approval', true),
            'emergency_stop' => $this->boolSetting('ai_manager.emergency_stop', false),
            'modes' => [
                AiManagerAction::MODE_OBSERVER => 'Observer',
                AiManagerAction::MODE_APPROVAL => 'Approval',
                AiManagerAction::MODE_BUDGETED => 'Budgeted Autonomy',
                AiManagerAction::MODE_AUTONOMOUS => 'Production Autonomy',
            ],
        ];
    }

    public function updatePolicy(User $user, array $data): void
    {
        $articlePercent = max(0, min(100, (float) ($data['article_fund_percent'] ?? 30)));

        $settings = [
            'mode' => $this->validMode((string) ($data['mode'] ?? AiManagerAction::MODE_OBSERVER)),
            'article_fund_percent' => (string) round($articlePercent, 2),
            'monthly_platform_ad_cap' => (string) round(max(0, (float) ($data['monthly_platform_ad_cap'] ?? 0)), 2),
            'max_actions_per_run' => (string) max(1, min(25, (int) ($data['max_actions_per_run'] ?? 6))),
            'allow_public_publishing' => ! empty($data['allow_public_publishing']) ? '1' : '0',
            'allow_direct_marketing' => ! empty($data['allow_direct_marketing']) ? '1' : '0',
            'allow_external_ad_spend' => ! empty($data['allow_external_ad_spend']) ? '1' : '0',
            'require_human_payout_approval' => ! empty($data['require_human_payout_approval']) ? '1' : '0',
            'emergency_stop' => ! empty($data['emergency_stop']) ? '1' : '0',
        ];

        foreach ($settings as $field => $value) {
            Setting::updateOrCreate(
                ['key' => "ai_manager.{$field}"],
                [
                    'value' => $value,
                    'type' => in_array($field, ['article_fund_percent', 'monthly_platform_ad_cap', 'max_actions_per_run'], true) ? 'number' : 'string',
                    'group' => 'ai_manager',
                    'updated_by_user_id' => $user->id,
                ]
            );
        }
    }

    public function generateRecommendations(User $actor): array
    {
        $policy = $this->policy();
        $kpis = $this->kpis();
        $allocation = $this->revenueAllocation($policy);
        $candidates = collect($this->recommendationCandidates($policy, $kpis, $allocation))
            ->sortByDesc(fn (array $action): float => (float) ($action['impact_score'] ?? 0))
            ->take((int) $policy['max_actions_per_run'])
            ->values();

        if ($candidates->isEmpty()) {
            $candidates = collect([
                [
                    'domain' => 'operations',
                    'action_type' => 'daily_operating_report',
                    'title' => 'Keep AI Manager in observer mode',
                    'summary' => 'No urgent queue thresholds were crossed. The manager should keep watching platform, advertising, article, and finance signals.',
                    'rationale' => 'A quiet operating day is still useful to log because it proves the manager checked the system.',
                    'risk_level' => 'low',
                    'required_mode' => AiManagerAction::MODE_OBSERVER,
                    'impact_score' => 20,
                    'confidence_score' => 95,
                    'payload' => ['kpis' => $kpis],
                ],
            ]);
        }

        $created = $candidates->map(fn (array $action): AiManagerAction => $this->createAction($action, $actor));

        return [
            'count' => $created->count(),
            'actions' => $created,
        ];
    }

    public function transitionAction(AiManagerAction $action, User $reviewer, string $status): AiManagerAction
    {
        $allowed = [
            AiManagerAction::STATUS_APPROVED,
            AiManagerAction::STATUS_DISMISSED,
            AiManagerAction::STATUS_BLOCKED,
            AiManagerAction::STATUS_EXECUTED,
        ];

        if (! in_array($status, $allowed, true)) {
            abort(422, 'Unsupported AI manager action status.');
        }

        $action->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'executed_at' => $status === AiManagerAction::STATUS_EXECUTED ? now() : $action->executed_at,
        ])->save();

        return $action;
    }

    public function revenueAllocation(?array $policy = null, ?CarbonInterface $month = null): array
    {
        $policy ??= $this->policy();
        $month ??= now();
        $revenue = $this->advertisingRevenueForMonth($month);
        $articleFund = round($revenue * ((float) $policy['article_fund_percent'] / 100), 2);
        $ownerShare = max(0, round($revenue - $articleFund, 2));
        $pendingWriterLiability = (float) ArticleWordLedger::query()
            ->where('status', 'pending')
            ->sum('gross_amount');

        return [
            'month' => $month->format('F Y'),
            'advertising_revenue' => $revenue,
            'article_fund' => $articleFund,
            'owner_share' => $ownerShare,
            'pending_writer_liability' => $pendingWriterLiability,
            'article_fund_remaining' => round($articleFund - $pendingWriterLiability, 2),
            'formatted_advertising_revenue' => $this->money($revenue),
            'formatted_article_fund' => $this->money($articleFund),
            'formatted_owner_share' => $this->money($ownerShare),
            'formatted_pending_writer_liability' => $this->money($pendingWriterLiability),
            'formatted_article_fund_remaining' => $this->money(round($articleFund - $pendingWriterLiability, 2)),
        ];
    }

    private function kpis(): array
    {
        $underperformingAds = AdCampaign::query()
            ->where('status', 'active')
            ->where('impressions', '>=', 100)
            ->whereRaw('(clicks * 100.0 / NULLIF(impressions, 0)) < 1')
            ->count();

        return [
            'ready_ads' => AdCampaign::query()->where('status', 'ready')->count(),
            'active_ads' => AdCampaign::query()->where('status', 'active')->count(),
            'underperforming_ads' => $underperformingAds,
            'pending_push_campaigns' => PushCampaign::query()
                ->whereNull('sent_at')
                ->whereIn('status', ['ready', 'scheduled', 'active'])
                ->count(),
            'pending_article_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_PENDING_REVIEW)->count(),
            'approved_article_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_APPROVED)->count(),
            'writer_articles_pending_review' => Article::query()->where('status', 'pending_review')->count(),
            'published_articles_30d' => Article::query()
                ->where('status', 'published')
                ->where('published_at', '>=', now()->subDays(30))
                ->count(),
            'failed_ai_generations_7d' => AiGeneration::query()
                ->where('status', AiGeneration::STATUS_FAILED)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'paid_payments_30d' => (float) Payment::query()
                ->where('status', 'paid')
                ->where('paid_at', '>=', now()->subDays(30))
                ->sum('amount'),
            'ai_budget' => $this->aiBudget->status(),
        ];
    }

    private function operatingBrief(array $policy, array $kpis, array $allocation): array
    {
        $warnings = [];

        if ($policy['emergency_stop']) {
            $warnings[] = 'Emergency stop is active. The manager may observe only.';
        }

        if ($kpis['failed_ai_generations_7d'] > 0) {
            $warnings[] = "{$kpis['failed_ai_generations_7d']} AI generation failure(s) need review.";
        }

        if ($allocation['article_fund_remaining'] < 0) {
            $warnings[] = 'Pending writer liabilities exceed the current article fund reserve.';
        }

        if (($kpis['ai_budget']['blocking_active'] ?? false) === true) {
            $warnings[] = 'AI monthly budget hard stop is active.';
        }

        return [
            'headline' => $this->briefHeadline($policy, $kpis, $allocation),
            'warnings' => $warnings,
            'next_step' => $policy['mode'] === AiManagerAction::MODE_OBSERVER
                ? 'Generate recommendations, review the action ledger, then move specific domains into approval mode.'
                : 'Review proposed actions and approve only the low-risk work that fits the current policy.',
        ];
    }

    private function briefHeadline(array $policy, array $kpis, array $allocation): string
    {
        if ($policy['emergency_stop']) {
            return 'AI Manager is paused by emergency stop.';
        }

        if ($kpis['ready_ads'] > 0 || $kpis['pending_push_campaigns'] > 0) {
            return 'Commercial queues need review before the platform can capture the next revenue opportunity.';
        }

        if ($allocation['article_fund'] > 0 && $allocation['article_fund_remaining'] > 0) {
            return 'The article fund has room to commission human-written local content.';
        }

        return 'AI Manager is watching the platform and building an audit trail before autonomy is expanded.';
    }

    private function domainSummary(): array
    {
        return AiManagerAction::query()
            ->select('domain', 'status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('domain', 'status')
            ->get()
            ->groupBy('domain')
            ->map(fn ($items, string $domain): array => [
                'domain' => $domain,
                'total' => (int) $items->sum('aggregate'),
                'proposed' => (int) $items->where('status', AiManagerAction::STATUS_PROPOSED)->sum('aggregate'),
                'approved' => (int) $items->where('status', AiManagerAction::STATUS_APPROVED)->sum('aggregate'),
                'executed' => (int) $items->where('status', AiManagerAction::STATUS_EXECUTED)->sum('aggregate'),
            ])
            ->sortBy('domain')
            ->values()
            ->all();
    }

    private function recommendationCandidates(array $policy, array $kpis, array $allocation): array
    {
        $actions = [];

        if ($policy['emergency_stop']) {
            return [[
                'domain' => 'safety',
                'action_type' => 'emergency_stop_review',
                'title' => 'Review emergency stop before granting autonomy',
                'summary' => 'Emergency stop is active, so the AI Manager should only observe and report.',
                'rationale' => 'This prevents accidental public, financial, or direct-marketing actions while the owner is testing guardrails.',
                'risk_level' => 'high',
                'required_mode' => AiManagerAction::MODE_OBSERVER,
                'impact_score' => 90,
                'confidence_score' => 100,
                'payload' => ['policy' => $policy],
            ]];
        }

        if ($kpis['ready_ads'] > 0) {
            $actions[] = [
                'domain' => 'advertising',
                'action_type' => 'review_ready_ads',
                'title' => 'Review '.$kpis['ready_ads'].' ready ad campaign'.$this->pluralSuffix($kpis['ready_ads']),
                'summary' => 'Ready ad campaigns can become revenue-visible once a human confirms placement, creative, dates, and sponsored labelling.',
                'rationale' => 'Public advertising remains an approval-mode task because it affects paid placement visibility and advertiser trust.',
                'risk_level' => 'medium',
                'required_mode' => AiManagerAction::MODE_APPROVAL,
                'impact_score' => 86,
                'confidence_score' => 90,
                'payload' => ['ready_ads' => $kpis['ready_ads']],
            ];
        }

        if ($kpis['pending_push_campaigns'] > 0) {
            $actions[] = [
                'domain' => 'growth',
                'action_type' => 'review_push_send_queue',
                'title' => 'Review '.$kpis['pending_push_campaigns'].' unsent push campaign'.$this->pluralSuffix($kpis['pending_push_campaigns']),
                'summary' => 'Unsent push campaigns should be checked for timing, audience fit, consent, and unsubscribe/compliance safety before dispatch.',
                'rationale' => 'Direct marketing should stay human-approved until policy proves the AI Manager can handle consent and targeting boundaries.',
                'risk_level' => 'high',
                'required_mode' => AiManagerAction::MODE_APPROVAL,
                'impact_score' => 82,
                'confidence_score' => 88,
                'payload' => ['pending_push_campaigns' => $kpis['pending_push_campaigns']],
            ];
        }

        if ($kpis['underperforming_ads'] > 0) {
            $actions[] = [
                'domain' => 'advertising',
                'action_type' => 'optimize_underperforming_ads',
                'title' => 'Improve '.$kpis['underperforming_ads'].' underperforming active ad'.$this->pluralSuffix($kpis['underperforming_ads']),
                'summary' => 'Active ads with enough impressions and low click-through should get fresh headlines, clearer calls to action, or better placement recommendations.',
                'rationale' => 'Optimization can remain low-risk when the AI drafts improvements without changing live campaigns automatically.',
                'risk_level' => 'low',
                'required_mode' => AiManagerAction::MODE_APPROVAL,
                'impact_score' => 72,
                'confidence_score' => 78,
                'payload' => ['underperforming_ads' => $kpis['underperforming_ads']],
            ];
        }

        if ($allocation['article_fund_remaining'] > 0 && $kpis['pending_article_briefs'] === 0) {
            $actions[] = [
                'domain' => 'editorial',
                'action_type' => 'commission_article_briefs',
                'title' => 'Commission article briefs from the article fund',
                'summary' => 'The current article fund reserve has room for human-written local content, and the brief review queue is empty.',
                'rationale' => 'This connects advertiser revenue directly to local human journalism without auto-publishing articles.',
                'risk_level' => 'medium',
                'required_mode' => AiManagerAction::MODE_APPROVAL,
                'impact_score' => 70,
                'confidence_score' => 80,
                'expected_value' => max(0, $allocation['article_fund_remaining']),
                'payload' => ['allocation' => $allocation],
            ];
        }

        if ($kpis['pending_article_briefs'] > 0) {
            $actions[] = [
                'domain' => 'editorial',
                'action_type' => 'review_article_briefs',
                'title' => 'Review '.$kpis['pending_article_briefs'].' pending editorial brief'.$this->pluralSuffix($kpis['pending_article_briefs']),
                'summary' => 'Pending AI-generated briefs should be approved, rejected, or sent back before Jimmy drafts articles from them.',
                'rationale' => 'Brief approval is the gate between research and paid article work.',
                'risk_level' => 'medium',
                'required_mode' => AiManagerAction::MODE_APPROVAL,
                'impact_score' => 68,
                'confidence_score' => 86,
                'payload' => ['pending_article_briefs' => $kpis['pending_article_briefs']],
            ];
        }

        if ($allocation['pending_writer_liability'] > 0) {
            $actions[] = [
                'domain' => 'finance',
                'action_type' => 'review_writer_liability',
                'title' => 'Review pending writer payment liability',
                'summary' => 'Pending writer ledgers total '.$allocation['formatted_pending_writer_liability'].' against an article fund reserve of '.$allocation['formatted_article_fund'].'.',
                'rationale' => 'The AI Manager can prepare payment visibility, but actual payout approval remains human-controlled.',
                'risk_level' => $allocation['article_fund_remaining'] < 0 ? 'high' : 'medium',
                'required_mode' => AiManagerAction::MODE_APPROVAL,
                'impact_score' => $allocation['article_fund_remaining'] < 0 ? 88 : 64,
                'confidence_score' => 92,
                'payload' => ['allocation' => $allocation],
            ];
        }

        if ($kpis['failed_ai_generations_7d'] > 0) {
            $actions[] = [
                'domain' => 'operations',
                'action_type' => 'inspect_ai_failures',
                'title' => 'Inspect '.$kpis['failed_ai_generations_7d'].' failed AI generation'.$this->pluralSuffix($kpis['failed_ai_generations_7d']),
                'summary' => 'Recent failed AI runs can hide provider, prompt, budget, or model-routing issues.',
                'rationale' => 'Autonomy should not expand while the AI layer has unresolved reliability errors.',
                'risk_level' => 'medium',
                'required_mode' => AiManagerAction::MODE_OBSERVER,
                'impact_score' => 66,
                'confidence_score' => 90,
                'payload' => ['failed_ai_generations_7d' => $kpis['failed_ai_generations_7d']],
            ];
        }

        return $actions;
    }

    private function createAction(array $data, User $actor): AiManagerAction
    {
        $payload = (array) ($data['payload'] ?? []);
        $keyMaterial = [
            'domain' => $data['domain'] ?? 'operations',
            'action_type' => $data['action_type'] ?? 'unknown',
            'title' => $data['title'] ?? '',
            'payload' => $payload,
            'day' => now()->toDateString(),
        ];

        return AiManagerAction::create([
            'action_key' => hash('sha256', json_encode($keyMaterial)),
            'domain' => (string) ($data['domain'] ?? 'operations'),
            'action_type' => (string) ($data['action_type'] ?? 'unknown'),
            'title' => (string) ($data['title'] ?? 'AI manager action'),
            'summary' => (string) ($data['summary'] ?? ''),
            'rationale' => (string) ($data['rationale'] ?? ''),
            'status' => AiManagerAction::STATUS_PROPOSED,
            'risk_level' => (string) ($data['risk_level'] ?? 'medium'),
            'required_mode' => (string) ($data['required_mode'] ?? AiManagerAction::MODE_APPROVAL),
            'impact_score' => (float) ($data['impact_score'] ?? 0),
            'confidence_score' => (float) ($data['confidence_score'] ?? 0),
            'estimated_cost' => (float) ($data['estimated_cost'] ?? 0),
            'expected_value' => (float) ($data['expected_value'] ?? 0),
            'payload' => $payload + ['proposed_by_user_id' => $actor->id],
        ]);
    }

    private function advertisingRevenueForMonth(CarbonInterface $month): float
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        return (float) OrderItem::query()
            ->whereHas('order.payments', function (Builder $query) use ($monthStart, $monthEnd): void {
                $query->where('status', 'paid')
                    ->whereBetween('paid_at', [$monthStart, $monthEnd]);
            })
            ->whereHas('package.type', function (Builder $query): void {
                $query->whereIn('slug', self::REVENUE_PACKAGE_TYPES);
            })
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as total')
            ->value('total');
    }

    private function numberSetting(string $key, float $default, float $min, float $max): float
    {
        $value = Setting::getValue($key, $default);

        return max($min, min($max, (float) $value));
    }

    private function boolSetting(string $key, bool $default): bool
    {
        $value = Setting::getValue($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function modeSetting(): string
    {
        return $this->validMode((string) Setting::getValue('ai_manager.mode', AiManagerAction::MODE_OBSERVER));
    }

    private function validMode(string $mode): string
    {
        return in_array($mode, [
            AiManagerAction::MODE_OBSERVER,
            AiManagerAction::MODE_APPROVAL,
            AiManagerAction::MODE_BUDGETED,
            AiManagerAction::MODE_AUTONOMOUS,
        ], true) ? $mode : AiManagerAction::MODE_OBSERVER;
    }

    private function money(float $amount): string
    {
        $currency = $this->costs->currency() === 'ZAR' ? 'R' : $this->costs->currency();

        return $currency.' '.number_format($amount, 2);
    }

    private function pluralSuffix(int $count): string
    {
        return $count === 1 ? '' : 's';
    }
}
