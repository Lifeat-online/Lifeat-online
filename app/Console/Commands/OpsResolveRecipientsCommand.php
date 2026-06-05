<?php

namespace App\Console\Commands;

use App\Services\OperatorPushNotifier;
use Illuminate\Console\Command;

class OpsResolveRecipientsCommand extends Command
{
    protected $signature = 'ops:resolve-recipients
        {target : The alert target name from config/ops.php}
        {--format=table : Output format (table|json|csv)}';

    protected $description = 'Preview which users will receive a given push target without sending.';

    public function handle(OperatorPushNotifier $notifier): int
    {
        $target = (string) $this->argument('target');

        if (! array_key_exists($target, (array) config('ops.targets', []))) {
            $this->error("Unknown target '{$target}'.");

            return self::FAILURE;
        }

        $recipients = $notifier->recipientsFor($target);
        $rows = $recipients->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'subscriptions' => $u->browserPushSubscriptions()->whereNull('revoked_at')->count(),
        ])->values()->all();

        $format = (string) $this->option('format');
        match ($format) {
            'json' => $this->line(json_encode($rows, JSON_PRETTY_PRINT)),
            'csv' => $this->writeCsv($rows),
            default => $this->table(
                ['id', 'name', 'email', 'role', 'subscriptions'],
                array_map(fn ($r) => array_values($r), $rows)
            ),
        };

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeCsv(array $rows): void
    {
        $out = fopen('php://stdout', 'w');
        if ($rows !== []) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
    }
}
