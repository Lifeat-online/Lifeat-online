<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DevTestRunnerService;
use App\Services\UpdateUtilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevUpdateController extends Controller
{
    public function status(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($updater->status());
    }

    public function credentials(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($updater->credentialsStatus());
    }

    public function saveCredentials(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'origin_url' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($updater) {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }

                if (! $updater->isValidOriginUrl(trim($value))) {
                    $fail('Origin URL must be a valid git repository URL (SSH or HTTPS).');
                }
            }],
            'token' => ['nullable', 'string', 'max:255'],
            'clear_token' => ['nullable', 'boolean'],
        ]);

        $originUrl = array_key_exists('origin_url', $validated) ? $validated['origin_url'] : null;
        $token = array_key_exists('token', $validated) ? $validated['token'] : null;
        $clearToken = (bool) ($validated['clear_token'] ?? false);

        return response()->json($updater->saveCredentials($originUrl, $token, $clearToken));
    }

    public function testCredentials(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($updater->testRemoteAccess());
    }

    public function apply(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($updater->apply());
    }

    public function runTests(Request $request, DevTestRunnerService $runner): JsonResponse
    {
        $this->ensureAdmin($request);

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

    private function ensureAdmin(Request $request): void
    {
        if (! $request->user()?->hasRole('super_admin')) {
            abort(403);
        }
    }

    private function testRunnerEnabled(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        return filter_var((string) env('DEV_TEST_RUNNER_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
    }
}
