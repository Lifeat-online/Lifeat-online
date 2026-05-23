<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\ResearchItem;
use App\Models\Setting;
use App\Services\AiBudgetService;
use App\Services\AiContentAssistantService;
use App\Services\AiCostEstimatorService;
use App\Services\AiImageService;
use App\Services\AskLifeService;
use App\Services\EditorialBriefService;
use App\Services\JimmyWritingService;
use App\Services\VoiceGatewayService;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiOperationsController extends Controller
{
    private const RETRYABLE_FEATURES = [
        'ask_life',
        'ask_life_voice',
        'article_image',
        'article_translation',
        'editorial_brief',
        'jimmy_article_draft',
    ];

    public function index(Request $request, AiPromptCatalog $prompts, AiCostEstimatorService $costs, AiBudgetService $budget): View
    {
        $filters = [
            'feature' => trim((string) $request->query('feature', '')),
            'status' => trim((string) $request->query('status', '')),
            'provider' => trim((string) $request->query('provider', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $generations = AiGeneration::query()
            ->with(['user', 'reviewer', 'source'])
            ->when($filters['feature'] !== '', fn (Builder $query) => $query->where('feature_key', $filters['feature']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['provider'] !== '', fn (Builder $query) => $query->where('provider', $filters['provider']))
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('input_summary', 'like', '%'.$filters['q'].'%')
                        ->orWhere('error_message', 'like', '%'.$filters['q'].'%')
                        ->orWhere('model', 'like', '%'.$filters['q'].'%')
                        ->orWhere('prompt_version', 'like', '%'.$filters['q'].'%');
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.ai-operations.index', [
            'generations' => $generations,
            'filters' => $filters,
            'featureOptions' => $this->distinct('feature_key'),
            'statusOptions' => $this->distinct('status'),
            'providerOptions' => $this->distinct('provider'),
            'statusStats' => $this->stats('status'),
            'providerStats' => $this->stats('provider'),
            'featureStats' => $this->stats('feature_key', 8),
            'costSummary' => $this->costSummary($costs),
            'budgetStatus' => $budget->status(),
            'costs' => $costs,
            'promptTemplates' => $prompts->all(),
            'canManageAiOperations' => $this->canManageAiOperations($request),
            'retryableFeatures' => self::RETRYABLE_FEATURES,
        ]);
    }

    public function updateBudget(Request $request): RedirectResponse
    {
        $this->ensureDevOwner($request);

        $validated = $request->validate([
            'monthly_limit_zar' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'warning_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'hard_stop_enabled' => ['nullable', 'boolean'],
            'exempt_features' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->setBudgetSetting($request, 'monthly_limit_zar', (string) round((float) ($validated['monthly_limit_zar'] ?? 0), 2));
        $this->setBudgetSetting($request, 'warning_percent', (string) round((float) $validated['warning_percent'], 2));
        $this->setBudgetSetting($request, 'hard_stop_enabled', $request->boolean('hard_stop_enabled') ? '1' : '0');
        $this->setBudgetSetting($request, 'exempt_features', trim((string) ($validated['exempt_features'] ?? '')));

        return back()->with('status', 'AI monthly budget settings saved.');
    }

    public function updatePrompt(Request $request, AiPromptCatalog $prompts, string $featureKey): RedirectResponse
    {
        $this->ensureDevOwner($request);
        $this->ensurePromptExists($prompts, $featureKey);

        $validated = $request->validate([
            'system' => ['required', 'string', 'max:8000'],
            'version' => ['nullable', 'string', 'max:120'],
            'output_language' => ['nullable', 'string', 'max:40'],
        ]);

        $this->setPromptSetting($request, $featureKey, 'system', trim($validated['system']));
        $this->setPromptSetting($request, $featureKey, 'version', trim((string) ($validated['version'] ?? '')));
        $this->setPromptSetting($request, $featureKey, 'output_language', trim((string) ($validated['output_language'] ?? '')));

        return back()->with('status', 'AI prompt override saved for '.str_replace('_', ' ', $featureKey).'.');
    }

    public function resetPrompt(Request $request, AiPromptCatalog $prompts, string $featureKey): RedirectResponse
    {
        $this->ensureDevOwner($request);
        $this->ensurePromptExists($prompts, $featureKey);

        Setting::query()
            ->whereIn('key', [
                "ai_prompt.{$featureKey}.system",
                "ai_prompt.{$featureKey}.version",
                "ai_prompt.{$featureKey}.output_language",
            ])
            ->delete();

        return back()->with('status', 'AI prompt override reset for '.str_replace('_', ' ', $featureKey).'.');
    }

    public function retry(
        Request $request,
        AiGeneration $aiGeneration,
        AskLifeService $askLife,
        VoiceGatewayService $voice,
        AiImageService $images,
        EditorialBriefService $briefs,
        JimmyWritingService $jimmy,
        AiContentAssistantService $content,
    ): RedirectResponse {
        $this->ensureDevOwner($request);

        if (! in_array($aiGeneration->feature_key, self::RETRYABLE_FEATURES, true)) {
            return back()->with('status', 'This AI generation cannot be retried from the operations panel yet.');
        }

        $payload = (array) $aiGeneration->input_payload;
        if ($payload === []) {
            return back()->with('status', 'This older AI generation has no stored input payload to retry.');
        }

        $result = match ($aiGeneration->feature_key) {
            'ask_life' => $this->retryAskLife($payload, $request, $askLife),
            'ask_life_voice' => $voice->speakAskLife((string) data_get($payload, 'text', ''), (string) data_get($payload, 'locale', 'en'), $request->user()),
            'article_image' => $this->retryArticleImage($aiGeneration, $payload, $request, $images),
            'editorial_brief' => $this->retryEditorialBrief($aiGeneration, $request, $briefs),
            'jimmy_article_draft' => $this->retryJimmyDraft($aiGeneration, $request, $jimmy),
            'article_translation' => $this->retryArticleTranslation($aiGeneration, $payload, $request, $content),
            default => ['ok' => false, 'message' => 'Unsupported retry target.'],
        };

        $this->markRetry($aiGeneration, $result);

        return back()->with('status', ($result['message'] ?? 'AI retry finished.'));
    }

    private function retryAskLife(array $payload, Request $request, AskLifeService $askLife): array
    {
        $question = (string) data_get($payload, 'question', '');
        if ($question === '') {
            return ['ok' => false, 'message' => 'Jimmy retry is missing its original question.'];
        }

        return $askLife->answer($question, $request->user());
    }

    private function retryArticleImage(AiGeneration $generation, array $payload, Request $request, AiImageService $images): array
    {
        $article = $generation->source instanceof Article
            ? $generation->source
            : Article::find((int) data_get($payload, 'article_id'));

        if (! $article) {
            return ['ok' => false, 'message' => 'Article image retry could not find its article.'];
        }

        return $images->generateForArticle($article, $request->user(), true);
    }

    private function retryEditorialBrief(AiGeneration $generation, Request $request, EditorialBriefService $briefs): array
    {
        if (! $generation->source instanceof ResearchItem) {
            return ['ok' => false, 'message' => 'Editorial brief retry could not find its research item.'];
        }

        return $briefs->generateForItem($generation->source, $request->user());
    }

    private function retryJimmyDraft(AiGeneration $generation, Request $request, JimmyWritingService $jimmy): array
    {
        if (! $generation->source instanceof ArticleBrief) {
            return ['ok' => false, 'message' => 'Jimmy retry could not find its article brief.'];
        }

        return $jimmy->draftFromBrief($generation->source, $request->user());
    }

    private function retryArticleTranslation(AiGeneration $generation, array $payload, Request $request, AiContentAssistantService $content): array
    {
        if (! $generation->source instanceof Article) {
            return ['ok' => false, 'message' => 'Article translation retry could not find its article.'];
        }

        $targetLocale = (string) data_get($payload, 'instructions.target_locale', $generation->output_language ?: 'af');

        return $content->translateArticle($generation->source, $targetLocale, $request->user(), true);
    }

    private function markRetry(AiGeneration $original, array $result): void
    {
        $generation = $result['generation'] ?? null;
        $generationId = $generation instanceof AiGeneration
            ? $generation->id
            : (int) ($result['generation_id'] ?? 0);

        if ($generationId > 0) {
            AiGeneration::query()
                ->whereKey($generationId)
                ->update(['retry_of_id' => $original->id]);
        }
    }

    private function distinct(string $column): array
    {
        return AiGeneration::query()
            ->whereNotNull($column)
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values()
            ->all();
    }

    private function stats(string $column, int $limit = 12): array
    {
        return AiGeneration::query()
            ->select($column, DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->{$column},
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    private function costSummary(AiCostEstimatorService $costs): array
    {
        $rows = AiGeneration::query()
            ->whereNotNull('cost_estimate')
            ->get(['provider', 'model', 'feature_key', 'cost_estimate', 'created_at']);

        $monthStart = now()->startOfMonth();

        return [
            'currency' => $costs->currency(),
            'exchange_rate' => $costs->exchangeRate(),
            'total' => (float) $rows->sum(fn (AiGeneration $generation): float => (float) $generation->cost_estimate),
            'current_month' => (float) $rows
                ->filter(fn (AiGeneration $generation): bool => $generation->created_at?->greaterThanOrEqualTo($monthStart) ?? false)
                ->sum(fn (AiGeneration $generation): float => (float) $generation->cost_estimate),
            'by_provider' => $this->costGroups($rows, 'provider', $costs),
            'by_feature' => $this->costGroups($rows, 'feature_key', $costs),
            'by_month' => $rows
                ->groupBy(fn (AiGeneration $generation): string => $generation->created_at?->format('Y-m') ?: 'unknown')
                ->map(fn ($items, string $label): array => [
                    'label' => $label,
                    'cost' => (float) $items->sum(fn (AiGeneration $generation): float => (float) $generation->cost_estimate),
                    'formatted' => $costs->format($items->sum(fn (AiGeneration $generation): float => (float) $generation->cost_estimate)),
                ])
                ->sortKeysDesc()
                ->take(6)
                ->values()
                ->all(),
        ];
    }

    private function costGroups($rows, string $column, AiCostEstimatorService $costs): array
    {
        return $rows
            ->groupBy(fn (AiGeneration $generation): string => (string) ($generation->{$column} ?: 'unknown'))
            ->map(fn ($items, string $label): array => [
                'label' => $label,
                'count' => $items->count(),
                'cost' => (float) $items->sum(fn (AiGeneration $generation): float => (float) $generation->cost_estimate),
                'formatted' => $costs->format($items->sum(fn (AiGeneration $generation): float => (float) $generation->cost_estimate)),
            ])
            ->sortByDesc('cost')
            ->take(8)
            ->values()
            ->all();
    }

    private function setPromptSetting(Request $request, string $featureKey, string $field, string $value): void
    {
        if ($value === '') {
            Setting::query()->where('key', "ai_prompt.{$featureKey}.{$field}")->delete();
            return;
        }

        Setting::updateOrCreate(
            ['key' => "ai_prompt.{$featureKey}.{$field}"],
            [
                'value' => $value,
                'type' => 'text',
                'group' => 'ai_prompts',
                'updated_by_user_id' => $request->user()?->id,
            ]
        );
    }

    private function setBudgetSetting(Request $request, string $field, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => "ai_budget.{$field}"],
            [
                'value' => $value,
                'type' => in_array($field, ['monthly_limit_zar', 'warning_percent'], true) ? 'number' : 'string',
                'group' => 'ai_budget',
                'updated_by_user_id' => $request->user()?->id,
            ]
        );
    }

    private function ensurePromptExists(AiPromptCatalog $prompts, string $featureKey): void
    {
        if (! $prompts->has($featureKey)) {
            abort(404);
        }
    }

    private function canManageAiOperations(Request $request): bool
    {
        return strtolower((string) $request->user()?->email) === 'jameskoen78@gmail.com';
    }

    private function ensureDevOwner(Request $request): void
    {
        if (! $this->canManageAiOperations($request)) {
            abort(403);
        }
    }
}
