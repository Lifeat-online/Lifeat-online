<?php

namespace App\Console\Commands;

use App\Models\OperatorAlertState;
use Illuminate\Console\Command;

class OpsListAlertsCommand extends Command
{
    protected $signature = 'ops:list-alerts
        {--severity= : Filter by severity (critical|warning|info)}
        {--unacknowledged-only : Show only unacknowledged alerts}
        {--limit=50}';

    protected $description = 'List recent operator push alerts from the operator_alert_states table.';

    public function handle(): int
    {
        $query = OperatorAlertState::query()->with('user')->latest('last_sent_at');

        if ($severity = $this->option('severity')) {
            $query->forSeverity((string) $severity);
        }

        if ($this->option('unacknowledged-only')) {
            $query->unacknowledged();
        }

        $alerts = $query->limit((int) $this->option('limit'))->get();

        if ($alerts->isEmpty()) {
            $this->info('No alerts match the filter.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'user', 'target', 'severity', 'retries', 'first_seen', 'last_sent', 'acknowledged'],
            $alerts->map(fn ($a) => [
                $a->id,
                $a->user?->email ?? 'deleted',
                $a->target,
                $a->severity,
                $a->retries_sent,
                $a->first_seen_at?->toIso8601String() ?? '-',
                $a->last_sent_at?->toIso8601String() ?? '-',
                $a->acknowledged_at?->toIso8601String() ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }
}
