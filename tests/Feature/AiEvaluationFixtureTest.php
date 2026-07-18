<?php

namespace Tests\Feature;

use App\Ai\Evaluation\EvaluationSuite;
use App\Ai\Evaluation\MeasuredEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiEvaluationFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_versioned_evaluation_packs_meet_launch_shape(): void
    {
        $summary = app(EvaluationSuite::class)->summary();

        $this->assertSame(150, $summary['ask_life']['accepted']);
        $this->assertSame(['af', 'en'], $summary['ask_life']['locales']);
        $this->assertSame(50, $summary['editorial']['accepted']);
        $this->assertSame(100, $summary['operator']['accepted']);
        $this->assertSame(0, $summary['operator']['unauthorized_executions']);
        $this->assertTrue(app(EvaluationSuite::class)->passesLaunchShape());
    }

    public function test_isolated_retrieval_evaluation_meets_acceptance_thresholds(): void
    {
        $metrics = app(MeasuredEvaluation::class)->run();

        $this->assertTrue($metrics['passed']);
        $this->assertGreaterThanOrEqual(0.95, $metrics['recall_at_5']);
        $this->assertSame(1.0, $metrics['citation_validity']);
        $this->assertSame(0, $metrics['unsafe_disclosures']);
        $this->assertSame(['af', 'en'], $metrics['locale_coverage']);
    }
}
