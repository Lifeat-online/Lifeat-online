<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\StaffWallet;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\WalletLedgerEntry;
use App\Services\StaffCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
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

    public function test_admin_can_record_manual_wallet_adjustments_with_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 100,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);

        $this->actingAs($admin)->post(route('admin.wallet.adjustments.store', $wallet), [
            'direction' => 'credit',
            'amount' => 75.25,
            'reason' => 'Approved accounting correction credit',
        ])->assertRedirect(route('admin.wallet.show', $wallet));

        $wallet->refresh();
        $this->assertSame('175.25', $wallet->available_balance);

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'entry_type' => WalletLedgerEntry::TYPE_ADJUSTMENT,
            'source_type' => StaffWallet::class,
            'source_id' => $wallet->id,
            'net_amount' => 75.25,
            'description' => 'Manual admin adjustment: Approved accounting correction credit',
        ]);

        $this->actingAs($admin)->post(route('admin.wallet.adjustments.store', $wallet), [
            'direction' => 'debit',
            'amount' => 25,
            'reason' => 'Approved accounting correction debit',
        ])->assertRedirect(route('admin.wallet.show', $wallet));

        $wallet->refresh();
        $this->assertSame('150.25', $wallet->available_balance);

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'entry_type' => WalletLedgerEntry::TYPE_ADJUSTMENT,
            'source_type' => StaffWallet::class,
            'source_id' => $wallet->id,
            'net_amount' => -25,
            'description' => 'Manual admin adjustment: Approved accounting correction debit',
        ]);

        $audit = AuditLog::where('action', 'staff_wallet.adjusted')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame(StaffWallet::class, $audit->subject_type);
        $this->assertSame($wallet->id, $audit->subject_id);
        $this->assertSame('175.25', $audit->before_json['available_balance']);
        $this->assertSame('150.25', $audit->after_json['available_balance']);
        $this->assertSame('debit', $audit->after_json['direction']);
        $this->assertEquals(25.0, $audit->after_json['amount']);
    }

    public function test_wallet_adjustment_debit_cannot_exceed_available_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 40,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);

        $this->actingAs($admin)->post(route('admin.wallet.adjustments.store', $wallet), [
            'direction' => 'debit',
            'amount' => 45,
            'reason' => 'Attempted over debit correction',
        ])->assertStatus(422);

        $wallet->refresh();
        $this->assertSame('40.00', $wallet->available_balance);
        $this->assertSame(0, WalletLedgerEntry::where('wallet_id', $wallet->id)->count());
        $this->assertSame(0, AuditLog::where('action', 'staff_wallet.adjusted')->count());
    }

    public function test_support_cannot_record_manual_wallet_adjustment(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 100,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);

        $this->actingAs($support)->post(route('admin.wallet.adjustments.store', $wallet), [
            'direction' => 'credit',
            'amount' => 10,
            'reason' => 'Support should not adjust wallets',
        ])->assertForbidden();

        $wallet->refresh();
        $this->assertSame('100.00', $wallet->available_balance);
        $this->assertSame(0, WalletLedgerEntry::where('wallet_id', $wallet->id)->count());
    }

    public function test_wallet_ledger_entries_are_append_only_through_model_events(): void
    {
        $entry = $this->manualLedgerEntry();

        try {
            $entry->update(['net_amount' => 999]);
            $this->fail('Wallet ledger entries should not be updateable through Eloquent.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Wallet ledger entries should not be deleteable through Eloquent.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'id' => $entry->id,
            'net_amount' => 15,
            'description' => 'Immutable ledger baseline',
        ]);
    }

    public function test_wallet_ledger_entries_are_append_only_at_database_level(): void
    {
        $entry = $this->manualLedgerEntry();

        try {
            DB::table('wallet_ledger_entries')
                ->where('id', $entry->id)
                ->update(['net_amount' => 999]);

            $this->fail('Wallet ledger entries should not be updateable through raw database queries.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        try {
            DB::table('wallet_ledger_entries')
                ->where('id', $entry->id)
                ->delete();

            $this->fail('Wallet ledger entries should not be deleteable through raw database queries.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'id' => $entry->id,
            'net_amount' => 15,
            'description' => 'Immutable ledger baseline',
        ]);
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

    private function manualLedgerEntry(): WalletLedgerEntry
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 15,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);

        return WalletLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'entry_type' => WalletLedgerEntry::TYPE_ADJUSTMENT,
            'source_type' => StaffWallet::class,
            'source_id' => $wallet->id,
            'gross_amount' => 15,
            'net_amount' => 15,
            'currency' => 'ZAR',
            'description' => 'Immutable ledger baseline',
            'recorded_at' => now(),
        ]);
    }
}
