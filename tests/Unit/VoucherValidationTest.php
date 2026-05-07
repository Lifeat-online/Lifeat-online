<?php

namespace Tests\Unit;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_voucher_is_active_within_date_range_and_inventory(): void
    {
        $voucher = Voucher::factory()->create([
            'status' => 'published',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'usage_limit' => 3,
            'redemptions_count' => 1,
        ]);

        $this->assertTrue($voucher->isCurrentlyActive());
        $this->assertSame(2, $voucher->remainingUses());
    }

    public function test_voucher_is_inactive_when_expired(): void
    {
        $voucher = Voucher::factory()->create([
            'status' => 'published',
            'end_at' => now()->subMinute(),
        ]);

        $this->assertFalse($voucher->isCurrentlyActive());
    }

    public function test_voucher_is_inactive_when_usage_limit_reached(): void
    {
        $voucher = Voucher::factory()->create([
            'status' => 'published',
            'usage_limit' => 2,
            'redemptions_count' => 2,
        ]);

        $this->assertFalse($voucher->isCurrentlyActive());
        $this->assertSame(0, $voucher->remainingUses());
    }
}

