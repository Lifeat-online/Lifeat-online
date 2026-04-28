<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Setting;
use App\Models\StaffWallet;
use App\Models\WalletLedgerEntry;

class StaffCommissionService
{
    /**
     * Credit commission to the referring staff member when a payment goes paid.
     * No-ops silently if the order has no staff attribution.
     */
    public function creditForPayment(Payment $payment): ?WalletLedgerEntry
    {
        $payment->loadMissing('order');
        $order = $payment->order;

        if (! $order || ! $order->referred_by_user_id) {
            return null;
        }

        $rate = (float) Setting::getValue('commission.rate', 0.10);

        if ($rate <= 0) {
            return null;
        }

        $commissionAmount = round((float) $payment->amount * $rate, 2);

        if ($commissionAmount <= 0) {
            return null;
        }

        $wallet = StaffWallet::firstOrCreate(
            ['user_id' => $order->referred_by_user_id],
            ['currency' => $order->currency ?? 'ZAR']
        );

        $ratePercent = $rate * 100;

        $entry = WalletLedgerEntry::create([
            'wallet_id'    => $wallet->id,
            'entry_type'   => WalletLedgerEntry::TYPE_COMMISSION_CREDIT,
            'source_type'  => Payment::class,
            'source_id'    => $payment->id,
            'gross_amount' => $commissionAmount,
            'net_amount'   => $commissionAmount,
            'currency'     => $wallet->currency,
            'description'  => "Commission on order #{$order->order_number} ({$ratePercent}%)",
            'recorded_at'  => now(),
        ]);

        $wallet->increment('available_balance', $commissionAmount);

        return $entry;
    }

    /**
     * Debit wallet and create ledger entry when a payout is marked paid.
     */
    public function debitForPayout(\App\Models\PayoutRequest $payout): WalletLedgerEntry
    {
        $wallet = $payout->wallet;

        $entry = WalletLedgerEntry::create([
            'wallet_id'         => $wallet->id,
            'payout_request_id' => $payout->id,
            'entry_type'        => WalletLedgerEntry::TYPE_PAYOUT_DEBIT,
            'source_type'       => \App\Models\PayoutRequest::class,
            'source_id'         => $payout->id,
            'gross_amount'      => $payout->amount,
            'net_amount'        => $payout->amount,
            'currency'          => $payout->currency,
            'description'       => "Payout #{$payout->id} paid by admin",
            'recorded_at'       => now(),
        ]);

        $wallet->decrement('available_balance', $payout->amount);
        $wallet->increment('paid_out_total', $payout->amount);

        return $entry;
    }
}
