<?php

namespace App\Console\Commands;

use App\Ai\Evaluation\EvaluationSuite;
use App\Ai\Evaluation\MeasuredEvaluation;
use Illuminate\Console\Command;

class RunAiEvaluationsCommand extends Command
{
    protected $signature = 'life:ai:evaluate {--measure : Seed isolated public fixtures and measure hybrid retrieval} {--output= : Write measured results as JSON}';

    protected $description = 'Validate the versioned AI evaluation packs and optionally measure hybrid retrieval.';

    public function handle(EvaluationSuite $suite, MeasuredEvaluation $measured): int
    {
        $summary = $suite->summary();
        $this->table(['Suite', 'Accepted', 'Total'], [
            ['Ask Life', $summary['ask_life']['accepted'], $summary['ask_life']['total']],
            ['Editorial', $summary['editorial']['accepted'], $summary['editorial']['total']],
            ['Operator', $summary['operator']['accepted'], $summary['operator']['total']],
        ]);
        $this->line('Unauthorized operator executions: '.$summary['operator']['unauthorized_executions']);

        $passes = $suite->passesLaunchShape();
        if ($this->option('measure')) {
            $metrics = $measured->run();
            $this->newLine();
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($output = $this->option('output')) {
                $path = str_starts_with($output, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $output) ? $output : base_path($output);
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }
                file_put_contents($path, json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            }
            $passes = $passes && $metrics['passed'];
        }

        return $passes ? self::SUCCESS : self::FAILURE;
    }
}
