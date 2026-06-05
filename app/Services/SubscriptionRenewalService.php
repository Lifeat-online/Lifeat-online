<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Subscription;
use App\Support\Logging\OperationalLog;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionRenewalService
{
    public function __construct(private readonly PayFastCheckoutService $payFastCheckoutService)
    {
    }

    public function createRenewalOrder(Subscription $subscription, bool $initiatePayment = false): Order
    {
        $subscription->loadMissing(['package.type', 'package.prices', 'subscribable', 'user']);

        $existingOrder = Order::where('renewed_subscription_id', $subscription->id)
            ->whereIn('status', ['pending_payment', 'paid'])
            ->latest('id')
            ->first();

        if ($existingOrder) {
            OperationalLog::info('subscription.renewal_order_reused', $this->renewalContext($subscription, $existingOrder));

            return $existingOrder;
        }

        $price = $subscription->package?->currentPrice();

        if (! $price) {
            throw new RuntimeException('The subscription package does not have an active price.');
        }

        $amount = (float) $price->amount;
        $vatPercentage = (float) Setting::getValue('billing.vat_percentage', 15);
        $vatAmount = $price->vat_inclusive ? round($amount - ($amount / (1 + ($vatPercentage / 100))), 2) : round($amount * ($vatPercentage / 100), 2);
        $subtotal = $price->vat_inclusive ? round($amount - $vatAmount, 2) : $amount;
        $total = $price->vat_inclusive ? $amount : round($amount + $vatAmount, 2);

        $order = DB::transaction(function () use ($subscription, $price, $subtotal, $vatAmount, $total, $initiatePayment) {
            $order = Order::create([
                'user_id' => $subscription->user_id,
                'renewed_subscription_id' => $subscription->id,
                'order_number' => 'REN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'status' => 'pending_payment',
                'currency' => $price->currency,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
            ]);

            $startAt = $subscription->ends_at && $subscription->ends_at->isFuture()
                ? $subscription->ends_at->copy()
                : now();

            OrderItem::create([
                'order_id' => $order->id,
                'package_id' => $subscription->package_id,
                'purchasable_type' => $subscription->subscribable_type,
                'purchasable_id' => $subscription->subscribable_id,
                'name_snapshot' => $subscription->package?->name.' Renewal',
                'unit_price' => $price->amount,
                'quantity' => 1,
                'billing_model' => $subscription->package?->billing_model ?? $subscription->renewal_mode,
                'starts_at' => $startAt,
                'ends_at' => $startAt->copy()->addDays($subscription->package?->duration_days ?? 30),
            ]);

            Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => (string) Setting::getValue('billing.invoice_prefix', 'LIFE').'-REN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'invoice_prefix_snapshot' => (string) Setting::getValue('billing.invoice_prefix', 'LIFE'),
                'status' => 'draft',
                'currency' => $price->currency,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'user_id' => $subscription->user_id,
                'provider' => 'payfast',
                'status' => 'pending',
                'amount' => $total,
                'currency' => $price->currency,
            ]);

            $order = $order->fresh(['user', 'items', 'payments', 'invoices']);

            if ($initiatePayment) {
                $payment = $order->latestPayment();

                if ($payment) {
                    $this->payFastCheckoutService->initiate(
                        $order,
                        $payment,
                        URL::route('checkout.payfast.callback'),
                        URL::route('checkout.show', $order),
                        URL::route('checkout.show', $order)
                    );
                }
            }

            return $order;
        });

        OperationalLog::info('subscription.renewal_order_created', $this->renewalContext($subscription, $order, [
            'payment_initiated' => $initiatePayment,
        ]));

        return $order;
    }

    private function renewalContext(Subscription $subscription, Order $order, array $extra = []): array
    {
        return array_merge([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'package_id' => $subscription->package_id,
            'subscribable_type' => $subscription->subscribable_type,
            'subscribable_id' => $subscription->subscribable_id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'amount' => (float) $order->total,
            'currency' => $order->currency,
        ], $extra);
    }
}
