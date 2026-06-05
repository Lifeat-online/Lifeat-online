<?php

namespace Tests\Feature;

use App\Models\PayoutRequest;
use App\Models\StaffWallet;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletPolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_own_account_wallet(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('account.wallet.index'))
            ->assertOk();

        $this->assertDatabaseHas('staff_wallets', [
            'user_id' => $staff->id,
            'currency' => 'ZAR',
        ]);
    }

    public function test_non_staff_cannot_view_account_wallet(): void
    {
        $user = User::factory()->create(['role' => 'business_owner']);

        $this->actingAs($user)
            ->get(route('account.wallet.index'))
            ->assertForbidden();
    }

    public function test_staff_cannot_view_admin_wallet_index(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.wallet.index'))
            ->assertForbidden();
    }

    public function test_support_can_view_admin_payout_requests_but_cannot_approve(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $payout = $this->requestedPayout();

        $this->actingAs($support)
            ->get(route('admin.payout-requests.index'))
            ->assertOk();

        $this->actingAs($support)
            ->post(route('admin.payout-requests.approve', $payout))
            ->assertForbidden();

        $this->actingAs($support)
            ->get(route('admin.payout-requests.export'))
            ->assertForbidden();
    }

    public function test_unrelated_staff_cannot_cancel_another_staff_payout_request(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $payout = $this->requestedPayout();

        $this->actingAs($staff)
            ->delete(route('account.wallet.payout-requests.cancel', $payout))
            ->assertForbidden();
    }

    public function test_admin_can_approve_requested_payout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payout = $this->requestedPayout();

        $this->actingAs($admin)
            ->post(route('admin.payout-requests.approve', $payout))
            ->assertRedirect(route('admin.payout-requests.show', $payout));

        $this->assertDatabaseHas('payout_requests', [
            'id' => $payout->id,
            'status' => PayoutRequest::STATUS_APPROVED,
            'reviewed_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_export_filtered_payout_requests_for_reconciliation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reviewer = User::factory()->create(['role' => 'admin', 'name' => 'Finance Reviewer']);
        $includedStaff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Export Included Staff',
            'email' => 'included-staff@example.test',
        ]);
        $excludedStaff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Export Excluded Staff',
            'email' => 'excluded-staff@example.test',
        ]);

        $includedWallet = StaffWallet::create([
            'user_id' => $includedStaff->id,
            'currency' => 'ZAR',
            'available_balance' => 350,
            'pending_balance' => 0,
            'paid_out_total' => 200,
        ]);
        $excludedWallet = StaffWallet::create([
            'user_id' => $excludedStaff->id,
            'currency' => 'ZAR',
            'available_balance' => 100,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);

        $includedPayout = PayoutRequest::create([
            'wallet_id' => $includedWallet->id,
            'requested_by_user_id' => $includedStaff->id,
            'reviewed_by_user_id' => $reviewer->id,
            'amount' => 200,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_PAID,
            'bank_name' => 'Export Bank',
            'account_holder' => 'Export Included Staff',
            'account_number' => '1234567890',
            'branch_code' => '250655',
            'payment_reference' => 'EFT-EXPORT-1',
            'notes' => 'Paid payout export note',
            'requested_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(2),
            'paid_at' => now()->subDay(),
        ]);
        WalletLedgerEntry::create([
            'wallet_id' => $includedWallet->id,
            'payout_request_id' => $includedPayout->id,
            'entry_type' => WalletLedgerEntry::TYPE_PAYOUT_DEBIT,
            'source_type' => PayoutRequest::class,
            'source_id' => $includedPayout->id,
            'gross_amount' => 200,
            'net_amount' => 200,
            'currency' => 'ZAR',
            'description' => 'Payout export debit',
            'recorded_at' => now()->subDay(),
        ]);

        PayoutRequest::create([
            'wallet_id' => $excludedWallet->id,
            'requested_by_user_id' => $excludedStaff->id,
            'amount' => 75,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_REQUESTED,
            'bank_name' => 'Other Bank',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payout-requests.export', [
            'status' => PayoutRequest::STATUS_PAID,
            'wallet' => $includedWallet->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Export Included Staff', $csv);
        $this->assertStringContainsString('included-staff@example.test', $csv);
        $this->assertStringContainsString('EFT-EXPORT-1', $csv);
        $this->assertStringContainsString('Export Bank', $csv);
        $this->assertStringContainsString('200.00', $csv);
        $this->assertStringNotContainsString('Export Excluded Staff', $csv);
        $this->assertStringNotContainsString('Other Bank', $csv);
    }

    private function requestedPayout(): PayoutRequest
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 100,
            'pending_balance' => 0,
            'paid_out_total' => 0,
        ]);

        return PayoutRequest::create([
            'wallet_id' => $wallet->id,
            'requested_by_user_id' => $staff->id,
            'amount' => 50,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_REQUESTED,
            'requested_at' => now(),
        ]);
    }
}
