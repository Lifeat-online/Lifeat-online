<?php

namespace App\Http\Controllers;

use App\Support\Monitoring\HealthReport;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthReport $health): JsonResponse
    {
        $report = $health->run();

        return response()->json($report, $report['status'] === 'down' ? 503 : 200);
    }
}
