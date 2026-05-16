<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\StaffWallet;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\StaffCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffWalletLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_credit_is_idempotent_for_same_payment(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $payment = $this->staffAttributedPayment($staff, $owner);

        $service = app(StaffCommissionService::class);
        $service->creditForPayment($payment);
        $service->creditForPayment($payment);

        $wallet = StaffWallet::where('user_id', $staff->id)->firstOrFail();

        $this->assertSame('250.00', $wallet->available_balance);
        $this->assertSame(1, WalletLedgerEntry::where('entry_type', WalletLedgerEntry::TYPE_COMMISSION_CREDIT)
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->count());
    }

    public function test_marking_approved_payout_paid_debits_wallet_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 300,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);
        $payout = PayoutRequest::create([
            'wallet_id' => $wallet->id,
            'requested_by_user_id' => $staff->id,
            'amount' => 120,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_APPROVED,
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.payout-requests.mark-paid', $payout), [
            'payment_reference' => 'BANK-123',
        ])->assertRedirect(route('admin.payout-requests.show', $payout));

        $wallet->refresh();
        $payout->refresh();

        $this->assertSame(PayoutRequest::STATUS_PAID, $payout->status);
        $this->assertSame('180.00', $wallet->available_balance);
        $this->assertSame('120.00', $wallet->paid_out_total);
        $this->assertDatabaseHas('wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'payout_request_id' => $payout->id,
            'entry_type' => WalletLedgerEntry::TYPE_PAYOUT_DEBIT,
            'source_type' => PayoutRequest::class,
            'source_id' => $payout->id,
            'net_amount' => 120,
        ]);

        $this->actingAs($admin)->post(route('admin.payout-requests.mark-paid', $payout))
            ->assertStatus(422);

        $wallet->refresh();
        $this->assertSame('180.00', $wallet->available_balance);
        $this->assertSame('120.00', $wallet->paid_out_total);
        $this->assertSame(1, WalletLedgerEntry::where('entry_type', WalletLedgerEntry::TYPE_PAYOUT_DEBIT)
            ->where('source_type', PayoutRequest::class)
            ->where('source_id', $payout->id)
            ->count());
    }

    public function test_full_refund_reverses_available_staff_commission_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $owner = User::factory()->create(['role' => 'business_owner']);
        $payment = $this->staffAttributedPayment($staff, $owner);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_transaction_id' => 'TX-STAFF-REFUND',
        ]);

        $wallet = StaffWallet::where('user_id', $staff->id)->firstOrFail();
        $this->assertSame('250.00', $wallet->available_balance);

        $this->actingAs($admin)->post(route('admin.finance.payments.refunds.store', $payment), [
            'refund_amount' => 500,
            'refund_reason' => 'Customer cancelled before fulfilment.',
        ])->assertRedirect(route('admin.finance.index'));

        $wallet->refresh();

        $this->assertSame('0.00', $wallet->available_balance);
        $this->assertSame(1, WalletLedgerEntry::where('entry_type', WalletLedgerEntry::TYPE_ADJUSTMENT)
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->count());

        app(StaffCommissionService::class)->reverseForRefund($payment);

        $wallet->refresh();
        $this->assertSame('0.00', $wallet->available_balance);
        $this->assertSame(1, WalletLedgerEntry::where('entry_type', WalletLedgerEntry::TYPE_ADJUSTMENT)
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->count());
    }

    private function staffAttributedPayment(User $staff, User $owner): Payment
    {
        $order = Order::create([
            'user_id' => $owner->id,
            'referred_by_user_id' => $staff->id,
            'order_number' => 'ORD-WALLET-'.strtoupper(fake()->bothify('????')),
            'status' => 'pending_payment',
            'currency' => 'ZAR',
            'subtotal' => 500,
            'vat_amount' => 0,
            'total' => 500,
        ]);

        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => 500,
            'currency' => 'ZAR',
        ]);
    }
}
