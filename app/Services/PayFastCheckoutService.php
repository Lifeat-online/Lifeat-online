<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Setting;
use Illuminate\Support\Arr;

class PayFastCheckoutService
{
    public function initiate(Order $order, Payment $payment, string $notifyUrl, string $returnUrl, string $cancelUrl): PaymentAttempt
    {
        $payload = [
            'merchant_id' => (string) Setting::getValue('payfast.merchant_id', ''),
            'merchant_key' => (string) Setting::getValue('payfast.merchant_key', ''),
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'notify_url' => $notifyUrl,
            'name_first' => $order->user?->name ?: 'Customer',
            'email_address' => $order->user?->email,
            'm_payment_id' => $order->order_number,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'item_name' => 'Life Platform Order '.$order->order_number,
            'custom_str1' => (string) $order->id,
        ];
        $payload['signature'] = $this->generateSignature($payload);

        $baseUrl = $this->isSandbox()
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process';

        return $payment->attempts()->create([
            'provider' => 'payfast',
            'status' => 'initiated',
            'request_payload_json' => $payload,
            'response_payload_json' => [
                'mode' => $this->isSandbox() ? 'sandbox' : 'live',
            ],
            'redirect_url' => $baseUrl.'?'.http_build_query($payload),
            'attempted_at' => now(),
        ]);
    }

    public function verifyCallback(array $payload): bool
    {
        if (empty($payload['signature'])) {
            return true;
        }

        $providedSignature = (string) $payload['signature'];
        $expectedSignature = $this->generateSignature(Arr::except($payload, ['signature']));

        return hash_equals($expectedSignature, $providedSignature);
    }

    public function generateSignature(array $payload): string
    {
        $filtered = collect($payload)
            ->reject(fn ($value, $key) => $value === null || $value === '' || $key === 'signature')
            ->sortKeys()
            ->map(fn ($value, $key) => $key.'='.urlencode((string) $value))
            ->implode('&');

        $passphrase = trim((string) Setting::getValue('payfast.passphrase', ''));

        if ($passphrase !== '') {
            $filtered .= '&passphrase='.urlencode($passphrase);
        }

        return md5($filtered);
    }

    private function isSandbox(): bool
    {
        return (string) Setting::getValue('payfast.use_sandbox', '1') === '1';
    }
}
