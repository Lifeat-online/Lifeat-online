<?php

namespace App\Jobs;

use App\Ai\Operator\OperatorTaskOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunOperatorTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly string $taskId) {}

    public function handle(OperatorTaskOrchestrator $orchestrator): void
    {
        $orchestrator->run($this->taskId);
    }
}
