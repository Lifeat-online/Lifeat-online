<?php

namespace App\Services;

use App\Events\MallOrderPaid;
use App\Models\MallCart;
use App\Models\MallOrder;
use App\Models\MallPayment;
use App\Support\Mall\MallMoney;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MallPayFastService
{
    public function buildPaymentData(MallOrder $order): array
    {
        $order->loadMissing('user', 'store');

        $fields = [
            'merchant_id' => (string) config('mall_payfast.merchant_id', ''),
            'merchant_key' => (string) config('mall_payfast.merchant_key', ''),
            'return_url' => route('mall.checkout.return', $order->store),
            'cancel_url' => route('mall.checkout.cancel', $order->store),
            'notify_url' => route('mall.payment.itn'),
            'm_payment_id' => $order->order_number,
            'amount' => $order->total,
            'item_name' => Str::limit('Life@ Mall '.$order->store->name.' '.$order->order_number, 100, ''),
            'email_address' => $order->user?->email,
            'name_first' => Str::limit((string) $order->user?->name, 100, ''),
            'item_description' => Str::limit('Life@ Mall order at '.$order->store->name, 255, ''),
        ];

        if ($order->store->hasPayFastSplit()) {
            $totalCents = MallMoney::toCents($order->total);
            $receiverPercent = $totalCents > 0
                ? round((MallMoney::toCents($order->vendor_amount) / $totalCents) * 100, 2)
                : 0;

            $fields['merchant_receiver'] = json_encode([
                'merchant_id' => $order->store->payfast_merchant_id,
                'merchant_key' => $order->store->payfast_merchant_key,
                'percentage' => $receiverPercent,
                'min' => 0,
                'max' => 0,
            ], JSON_THROW_ON_ERROR);
        }

        $fields = array_filter($fields, fn ($value) => $value !== null && $value !== '');
        $fields['signature'] = $this->generateSignature($fields);

        return [
            'url' => $this->processUrl(),
            'fields' => $fields,
        ];
    }

    public function validateItn(array $payload): bool
    {
        if (empty($payload['signature'])) {
            return false;
        }

        $providedSignature = (string) $payload['signature'];
        $expectedSignature = $this->generateSignature(Arr::except($payload, ['signature']));

        if (! hash_equals($expectedSignature, $providedSignature)) {
            return false;
        }

        if (! config('mall_payfast.validate_itn_with_server', false)) {
            return true;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post($this->validateUrl(), $payload);

        return trim($response->body()) === 'VALID';
    }

    public function processSuccessfulItn(array $payload): bool
    {
        return DB::transaction(function () use ($payload): bool {
            $payment = MallPayment::query()
                ->where('m_payment_id', $payload['m_payment_id'] ?? null)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return false;
            }

            if ($payment->status === 'complete') {
                return true;
            }

            $order = $payment->order()
                ->with('items.product', 'store')
                ->firstOrFail();

            if (($payload['payment_status'] ?? null) !== 'COMPLETE') {
                $payment->update([
                    'status' => 'failed',
                    'itn_payload' => $payload,
                ]);

                return false;
            }

            if (MallMoney::toCents($payload['amount_gross'] ?? null) !== MallMoney::toCents($order->total)) {
                $payment->update([
                    'status' => 'failed',
                    'itn_payload' => $payload,
                ]);

                return false;
            }

            foreach ($order->items as $item) {
                $product = $item->product;

                if (! $product || ! $product->manage_stock) {
                    continue;
                }

                if ($product->stock_qty < $item->quantity) {
                    return false;
                }
            }

            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product && $product->manage_stock) {
                    $product->decrement('stock_qty', $item->quantity);
                }
            }

            $payment->update([
                'status' => 'complete',
                'payfast_payment_id' => $payload['pf_payment_id'] ?? null,
                'itn_payload' => $payload,
                'payfast_fee' => $payload['amount_fee'] ?? null,
                'net_amount' => $payload['amount_net'] ?? null,
            ]);

            $order->update([
                'status' => 'paid',
                'payfast_payment_id' => $payload['pf_payment_id'] ?? null,
                'paid_at' => now(),
            ]);

            MallCart::query()
                ->where('user_id', $order->user_id)
                ->where('mall_store_id', $order->mall_store_id)
                ->delete();

            app(MallTransportDeliveryService::class)->dispatchPaidOrder($order->fresh(['fulfillment', 'store']));
            app(MallPudoService::class)->createShipmentForPaidOrder($order->fresh(['fulfillment', 'store', 'user', 'items']));

            MallOrderPaid::dispatch($order->fresh());

            return true;
        });
    }

    public function generateSignature(array $payload): string
    {
        $signaturePayload = collect($payload)
            ->reject(fn ($value, $key) => $value === null || $value === '' || $key === 'signature')
            ->map(fn ($value, $key) => $key.'='.urlencode((string) $value))
            ->implode('&');

        $passphrase = trim((string) config('mall_payfast.passphrase', ''));

        if ($passphrase !== '') {
            $signaturePayload .= '&passphrase='.urlencode($passphrase);
        }

        return md5($signaturePayload);
    }

    private function processUrl(): string
    {
        return config('mall_payfast.testmode', true)
            ? (string) config('mall_payfast.sandbox_process_url')
            : (string) config('mall_payfast.production_process_url');
    }

    private function validateUrl(): string
    {
        return config('mall_payfast.testmode', true)
            ? (string) config('mall_payfast.sandbox_validate_url')
            : (string) config('mall_payfast.production_validate_url');
    }
}
