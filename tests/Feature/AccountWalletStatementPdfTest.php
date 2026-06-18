<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Models\StaffWallet;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountWalletStatementPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_download_their_pdf_statement(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Test Staff']);

        $wallet = StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 123.45,
            'pending_balance' => 10.00,
            'paid_out_total' => 500.00,
        ]);

        $response = $this->actingAs($staff)->get(route('account.wallet.statement.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertGreaterThan(500, strlen($response->getContent()));
    }

    public function test_pdf_response_is_a_non_empty_pdf(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Statement Holder',
            'email' => 'holder@example.test',
        ]);

        StaffWallet::create([
            'user_id' => $staff->id,
            'currency' => 'ZAR',
            'available_balance' => 250.00,
            'pending_balance' => 0.00,
            'paid_out_total' => 100.00,
        ]);

        $response = $this->actingAs($staff)->get(route('account.wallet.statement.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function test_pdf_serves_with_inline_filename(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        StaffWallet::create(['user_id' => $staff->id, 'currency' => 'ZAR']);

        $response = $this->actingAs($staff)->get(route('account.wallet.statement.pdf'));

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('lifeat-payout-statement-', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_pdf_reflects_payout_request_data(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = StaffWallet::create(['user_id' => $staff->id, 'currency' => 'ZAR']);

        PayoutRequest::create([
            'wallet_id' => $wallet->id,
            'requested_by_user_id' => $staff->id,
            'amount' => 250.00,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_REQUESTED,
            'bank_name' => 'Test Bank',
            'account_holder' => $staff->name,
            'account_number' => '1234567890',
            'branch_code' => '000000',
            'requested_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($staff)->get(route('account.wallet.statement.pdf'));

        $response->assertOk();
        $this->assertGreaterThan(800, strlen($response->getContent()));

        $this->assertDatabaseHas('payout_requests', [
            'wallet_id' => $wallet->id,
            'bank_name' => 'Test Bank',
            'account_number' => '1234567890',
        ]);
    }

    public function test_pdf_view_masks_account_number_except_last_four(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = new StaffWallet(['user_id' => $staff->id, 'currency' => 'ZAR']);
        $wallet->id = 1;

        $payout = new PayoutRequest([
            'amount' => 100,
            'currency' => 'ZAR',
            'status' => PayoutRequest::STATUS_PAID,
            'bank_name' => 'First National',
            'account_number' => '9876543210',
            'requested_at' => now()->subDays(5),
            'processed_at' => now()->subDays(2),
        ]);

        $rendered = view('account.wallet.statement-pdf', [
            'wallet' => $wallet,
            'payoutRequests' => collect([$payout]),
            'ledgerEntries' => collect(),
            'holder' => $staff,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('••••••3210', $rendered);
        $this->assertStringNotContainsString('9876543210', $rendered);
    }

    public function test_pdf_view_includes_ledger_descriptions(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $wallet = new StaffWallet(['user_id' => $staff->id, 'currency' => 'ZAR']);
        $wallet->id = 1;

        $entry = new WalletLedgerEntry([
            'entry_type' => 'commission_credit',
            'amount' => 75,
            'currency' => 'ZAR',
            'net_amount' => 75,
            'description' => 'Commission from order #TEST-1234',
            'source_type' => 'order',
            'source_id' => 1234,
            'recorded_at' => now()->subDay(),
        ]);

        $rendered = view('account.wallet.statement-pdf', [
            'wallet' => $wallet,
            'payoutRequests' => collect(),
            'ledgerEntries' => collect([$entry]),
            'holder' => $staff,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Commission from order #TEST-1234', $rendered);
    }

    public function test_generation_is_audited(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        StaffWallet::create(['user_id' => $staff->id, 'currency' => 'ZAR']);

        $this->actingAs($staff)->get(route('account.wallet.statement.pdf'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $staff->id,
            'action' => 'wallet.statement_pdf_generated',
            'subject_type' => StaffWallet::class,
        ]);
    }

    public function test_guests_cannot_download_the_statement(): void
    {
        $response = $this->get(route('account.wallet.statement.pdf'));

        $response->assertRedirect();
    }

    public function test_non_staff_role_is_forbidden(): void
    {
        $member = User::factory()->create(['role' => 'registered_user']);
        StaffWallet::create(['user_id' => $member->id, 'currency' => 'ZAR']);

        $response = $this->actingAs($member)->get(route('account.wallet.statement.pdf'));

        $response->assertForbidden();
    }

    public function test_creates_wallet_on_first_download(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertDatabaseMissing('staff_wallets', ['user_id' => $staff->id]);

        $this->actingAs($staff)->get(route('account.wallet.statement.pdf'))->assertOk();

        $this->assertDatabaseHas('staff_wallets', ['user_id' => $staff->id]);
    }
}
