<?php

namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Services\MallPayFastService;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private MallPayFastService $payFastService) {}

    public function itn(Request $request): Response
    {
        $payload = $request->all();
        Log::info('Mall PayFast ITN received', $payload);

        if (! $this->payFastService->validateItn($payload)) {
            Log::error('Mall PayFast ITN validation failed', $payload);

            return response('', 200);
        }

        if (! $this->payFastService->processSuccessfulItn($payload)) {
            Log::error('Mall PayFast ITN processing failed', $payload);
        }

        return response('', 200);
    }
}
