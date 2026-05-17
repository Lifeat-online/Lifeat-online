<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DevTestRunnerService;
use App\Services\VapidKeySetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DevUpdateController extends Controller
{
    public function runTests(Request $request, DevTestRunnerService $runner): JsonResponse
    {
        $this->ensureDevOwner($request);
        $this->ensureDevToolsAvailable();

        if (! $this->testRunnerEnabled()) {
            return response()->json([
                'ok' => false,
                'message' => 'Test runner is disabled in this environment.',
            ], 403);
        }

        $validated = $request->validate([
            'suite' => ['nullable', 'string', 'in:all,Unit,Feature'],
        ]);

        $suite = $validated['suite'] ?? 'all';
        $result = $runner->run($suite);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 400);
    }

    public function enableVapidKeys(Request $request, VapidKeySetupService $vapidKeys): JsonResponse
    {
        $this->ensureDevOwner($request);

        try {
            $result = $vapidKeys->enable();
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'status' => $vapidKeys->status(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
    }

    private function ensureDevOwner(Request $request): void
    {
        if (strtolower((string) $request->user()?->email) !== 'jameskoen78@gmail.com') {
            abort(403);
        }
    }

    private function testRunnerEnabled(): bool
    {
        if (in_array((string) config('app.env'), ['local', 'testing'], true)) {
            return true;
        }

        return $this->devToolsAvailable()
            && filter_var((string) env('DEV_TEST_RUNNER_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
    }

    private function ensureDevToolsAvailable(): void
    {
        if (! $this->devToolsAvailable()) {
            abort(403, 'Developer tools are disabled in this environment.');
        }
    }

    private function devToolsAvailable(): bool
    {
        if (in_array((string) config('app.env'), ['local', 'testing'], true)) {
            return true;
        }

        return filter_var((string) env('DEV_TOOLS_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
    }
}
