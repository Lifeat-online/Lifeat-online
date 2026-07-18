<?php

namespace App\Console\Commands;

use App\Ai\Evaluation\EvaluationSuite;
use Illuminate\Console\Command;

class RunAiEvaluationsCommand extends Command
{
    protected $signature = 'life:ai:evaluate';
    protected $description = 'Validate the versioned Ask Life, editorial, and operator evaluation packs.';

    public function handle(EvaluationSuite $suite): int
    {
        $summary = $suite->summary();
        $this->table(['Suite', 'Accepted', 'Total'], [
            ['Ask Life', $summary['ask_life']['accepted'], $summary['ask_life']['total']],
            ['Editorial', $summary['editorial']['accepted'], $summary['editorial']['total']],
            ['Operator', $summary['operator']['accepted'], $summary['operator']['total']],
        ]);
        $this->line('Unauthorized operator executions: '.$summary['operator']['unauthorized_executions']);

        return $suite->passesLaunchShape() ? self::SUCCESS : self::FAILURE;
    }
}
