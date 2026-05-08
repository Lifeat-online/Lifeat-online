<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UpdateUtilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevUpdateController extends Controller
{
    public function status(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }

        return response()->json($updater->status());
    }

    public function credentials(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }

        return response()->json($updater->credentialsStatus());
    }

    public function saveCredentials(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }

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
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }

        return response()->json($updater->testRemoteAccess());
    }

    public function apply(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }

        return response()->json($updater->apply());
    }
}
