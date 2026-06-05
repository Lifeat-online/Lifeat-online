<?php

namespace App\Console\Commands;

use App\Services\OperatorPushNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OpsCheckQueueDepthCommand extends Command
{
    protected $signature = 'ops:check-queue-depth
        {--connection= : Queue connection (default: config(ops.queue.connection))}
        {--queue= : Queue name (default: config(ops.queue.queue_name))}';

    protected $description = 'Check the default queue depth and push a warning/critical alert when the backlog is too high.';

    public function handle(OperatorPushNotifier $notifier): int
    {
        $connection = (string) ($this->option('connection') ?: config('ops.queue.connection', 'database'));
        $queueName = (string) ($this->option('queue') ?: config('ops.queue.queue_name', 'default'));

        $depth = 0;
        $available = false;

        try {
            if ($connection === 'database') {
                $depth = (int) DB::table('jobs')
                    ->where('queue', $queueName)
                    ->count();
                $available = true;
            } else {
                $size = \Illuminate\Support\Facades\Queue::size($queueName);
                $depth = is_null($size) ? 0 : (int) $size;
                $available = true;
            }
        } catch (\Throwable $e) {
            $this->warn("Queue depth check failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $available) {
            $this->warn("Could not measure queue depth for connection={$connection} queue={$queueName}.");
        }

        $this->info("Queue depth ({$connection}/{$queueName}): {$depth}");

        $warningAt = (int) config('ops.queue.depth_warning', 500);
        $criticalAt = (int) config('ops.queue.depth_critical', 2000);

        if ($depth >= $criticalAt) {
            $notifier->send(
                target: 'queue:backlog',
                title: 'Queue backlog CRITICAL',
                body: sprintf('%s queue has %d jobs (threshold %d).', $queueName, $depth, $criticalAt),
                severity: 'critical',
                url: 'https://lifeat.online/admin/dashboard',
                data: ['connection' => $connection, 'queue' => $queueName, 'depth' => $depth]
            );
        } elseif ($depth >= $warningAt) {
            $notifier->send(
                target: 'queue:backlog',
                title: 'Queue backlog warning',
                body: sprintf('%s queue has %d jobs (threshold %d).', $queueName, $depth, $warningAt),
                severity: 'warning',
                url: 'https://lifeat.online/admin/dashboard',
                data: ['connection' => $connection, 'queue' => $queueName, 'depth' => $depth]
            );
        }

        return self::SUCCESS;
    }
}
