<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Operator\OperatorToolRuntime;
use App\Ai\Operator\OperatorApprovalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiOperatorController extends Controller
{
    public function execute(Request $request, string $tool, OperatorToolRuntime $runtime): JsonResponse
    {
        $validated = $request->validate([
            'arguments' => ['present', 'array'],
            'idempotency_key' => ['required', 'string', 'max:200'],
            'approval_token' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json($runtime->execute($request->user(), $tool, $validated['arguments'], $validated['idempotency_key'], $validated['approval_token'] ?? null));
    }

    public function approve(Request $request, string $tool, OperatorApprovalService $approvals): JsonResponse
    {
        $validated = $request->validate(['arguments' => ['present', 'array']]);

        return response()->json($approvals->issue($request->user(), $tool, $validated['arguments']));
    }
}
