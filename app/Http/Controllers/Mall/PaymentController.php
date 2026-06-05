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
        $redacted = $this->redactItnPayload($payload);

        Log::info('Mall PayFast ITN received', $redacted);

        if (! $this->payFastService->validateItn($payload)) {
            Log::error('Mall PayFast ITN validation failed', $redacted);

            return response('', 200);
        }

        if (! $this->payFastService->processSuccessfulItn($payload)) {
            Log::error('Mall PayFast ITN processing failed', $redacted);
        }

        return response('', 200);
    }

    private function redactItnPayload(array $payload): array
    {
        $keys = [
            'm_payment_id', 'pf_payment_id', 'payment_status', 'item_name',
            'item_description', 'amount_gross', 'amount_fee', 'amount_net',
            'custom_str1', 'custom_str2', 'custom_str3', 'custom_str4', 'custom_str5',
            'name_first', 'name_last', 'email_address', 'merchant_id',
            'token', 'billing_date',
        ];

        return collect($payload)
            ->only($keys)
            ->mapWithKeys(fn ($value, $key) => [
                $key => in_array($key, ['token', 'merchant_id'], true) ? '[redacted]' : $value,
            ])
            ->all();
    }
}
