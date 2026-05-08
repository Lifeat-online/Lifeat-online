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

    public function apply(Request $request, UpdateUtilityService $updater): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }

        return response()->json($updater->apply());
    }
}

