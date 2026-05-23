<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class AiBudgetService
{
    public function __construct(
        private readonly AiCostEstimatorService $costs,
    ) {
    }

    public function status(?CarbonInterface $month = null): array
    {
        $limit = $this->monthlyLimit();
        $spent = $this->monthlySpend($month);
        $warningPercent = $this->warningPercent();
        $percentUsed = $limit > 0 ? min(999, ($spent / $limit) * 100) : 0;
        $warningAt = $limit > 0 ? ($limit * ($warningPercent / 100)) : 0;
        $isWarning = $limit > 0 && $spent >= $warningAt;
        $isExceeded = $limit > 0 && $spent >= $limit;
        $hardStop = $this->hardStopEnabled();

        return [
            'currency' => $this->costs->currency(),
            'limit' => $limit,
            'spent' => $spent,
            'remaining' => $limit > 0 ? max(0, $limit - $spent) : null,
            'warning_percent' => $warningPercent,
            'warning_at' => $warningAt,
            'percent_used' => $percentUsed,
            'warning' => $isWarning,
            'exceeded' => $isExceeded,
            'hard_stop_enabled' => $hardStop,
            'blocking_active' => $isExceeded && $hardStop,
            'exempt_features' => $this->exemptFeatures(),
            'formatted_limit' => $limit > 0 ? $this->costs->format($limit) : 'No limit',
            'formatted_spent' => $this->costs->format($spent),
            'formatted_remaining' => $limit > 0 ? $this->costs->format(max(0, $limit - $spent)) : '-',
            'formatted_warning_at' => $limit > 0 ? $this->costs->format($warningAt) : '-',
            'message' => $this->message($limit, $spent, $percentUsed, $isWarning, $isExceeded, $hardStop),
        ];
    }

    public function blockReason(string $featureKey): ?string
    {
        if ($this->isExempt($featureKey)) {
            return null;
        }

        $status = $this->status();

        if (! ($status['blocking_active'] ?? false)) {
            return null;
        }

        return 'AI monthly budget reached. '.$status['formatted_spent'].' spent against '.$status['formatted_limit'].'. Non-essential AI jobs are paused until the budget is raised, hard stop is disabled, or the month resets.';
    }

    public function monthlySpend(?CarbonInterface $month = null): float
    {
        $month ??= now();

        return (float) AiGeneration::query()
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereNotNull('cost_estimate')
            ->sum('cost_estimate');
    }

    public function monthlyLimit(): float
    {
        $setting = Setting::getValue('ai_budget.monthly_limit_zar');
        $value = $setting !== null ? $setting : config('ai_costs.budget.monthly_limit_zar', 0);

        return max(0, (float) $value);
    }

    public function warningPercent(): float
    {
        $setting = Setting::getValue('ai_budget.warning_percent');
        $value = $setting !== null ? $setting : config('ai_costs.budget.warning_percent', 80);

        return max(1, min(100, (float) $value));
    }

    public function hardStopEnabled(): bool
    {
        $setting = Setting::getValue('ai_budget.hard_stop_enabled');

        if ($setting !== null) {
            return filter_var($setting, FILTER_VALIDATE_BOOL);
        }

        return filter_var(config('ai_costs.budget.hard_stop_enabled', false), FILTER_VALIDATE_BOOL);
    }

    public function exemptFeatures(): array
    {
        $configured = collect((array) config('ai_costs.budget.exempt_features', []));
        $saved = (string) Setting::getValue('ai_budget.exempt_features', '');

        return $configured
            ->merge(preg_split('/[\s,]+/', $saved) ?: [])
            ->map(fn ($feature): string => trim((string) $feature))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isExempt(string $featureKey): bool
    {
        return in_array($featureKey, $this->exemptFeatures(), true);
    }

    private function message(float $limit, float $spent, float $percentUsed, bool $warning, bool $exceeded, bool $hardStop): string
    {
        if ($limit <= 0) {
            return 'No monthly AI budget limit is configured.';
        }

        if ($exceeded && $hardStop) {
            return 'Monthly AI budget is reached and hard stop is active.';
        }

        if ($exceeded) {
            return 'Monthly AI budget is reached. Hard stop is off, so jobs can still run.';
        }

        if ($warning) {
            return 'Monthly AI budget warning threshold reached at '.number_format($percentUsed, 1).'%.';
        }

        return 'Monthly AI spend is within budget.';
    }
}
