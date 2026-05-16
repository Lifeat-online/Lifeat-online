<?php

namespace Tests\Feature;

use App\Models\PayoutRequest;
use App\Models\StaffWallet;
use App\Models\User;
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
