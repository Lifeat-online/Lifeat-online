<?php

namespace Tests\Feature;

use App\Ai\Evaluation\EvaluationSuite;
use Tests\TestCase;

class AiEvaluationFixtureTest extends TestCase
{
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
}
