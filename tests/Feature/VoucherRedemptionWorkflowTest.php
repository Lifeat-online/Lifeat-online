<?php

namespace Tests\Feature;

use App\Mail\VoucherRedeemedMail;
use App\Models\Listing;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VoucherRedemptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_redeem_voucher_and_receives_email(): void
    {
        Mail::fake();

        $customer = User::factory()->create();
        $listingOwner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $listingOwner->id,
            'status' => 'published',
        ]);
        $voucher = Voucher::factory()->create([
            'listing_id' => $listing->id,
            'status' => 'published',
            'start_at' => now()->subHour(),
            'end_at' => now()->addDay(),
            'usage_limit' => 2,
            'redemptions_count' => 0,
        ]);

        $response = $this->actingAs($customer)->post(route('vouchers.redeem', [$listing, $voucher]));

        $response->assertRedirect(route('account.vouchers.index'));
        $this->assertDatabaseHas('voucher_redemptions', [
            'voucher_id' => $voucher->id,
            'user_id' => $customer->id,
            'status' => 'claimed',
        ]);

        $voucher->refresh();
        $this->assertSame(1, (int) $voucher->redemptions_count);

        Mail::assertSent(VoucherRedeemedMail::class);
    }

    public function test_redemption_fails_when_usage_limit_reached(): void
    {
        $customer = User::factory()->create();
        $listingOwner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $listingOwner->id,
            'status' => 'published',
        ]);
        $voucher = Voucher::factory()->create([
            'listing_id' => $listing->id,
            'status' => 'published',
            'start_at' => now()->subHour(),
            'end_at' => now()->addDay(),
            'usage_limit' => 1,
            'redemptions_count' => 1,
        ]);

        $response = $this->actingAs($customer)->post(route('vouchers.redeem', [$listing, $voucher]));

        $response->assertRedirect(route('vouchers.show', [$listing, $voucher]));
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('voucher_redemptions', [
            'voucher_id' => $voucher->id,
            'user_id' => $customer->id,
        ]);
    }

    public function test_business_owner_can_consume_claimed_voucher(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'published',
        ]);
        $voucher = Voucher::factory()->create([
            'listing_id' => $listing->id,
            'status' => 'published',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);
        $redemption = VoucherRedemption::factory()->create([
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
            'claimed_at' => now(),
            'consumed_at' => null,
            'consumed_by_user_id' => null,
        ]);

        $response = $this->actingAs($owner)->post(route('staff.vouchers.consume'), [
            'code' => $redemption->code,
        ]);

        $response->assertRedirect(route('staff.vouchers.redeem', ['code' => $redemption->code]));
        $redemption->refresh();
        $this->assertSame('consumed', $redemption->status);
        $this->assertNotNull($redemption->consumed_at);
        $this->assertSame($owner->id, $redemption->consumed_by_user_id);
    }
}

