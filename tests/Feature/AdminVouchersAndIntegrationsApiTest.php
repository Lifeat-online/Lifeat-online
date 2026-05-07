<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\MarketingIntegration;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVouchersAndIntegrationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_voucher_via_admin_api(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $listing = Listing::factory()->create([
            'status' => 'published',
        ]);

        $payload = [
            'listing_id' => $listing->id,
            'title' => 'Launch discount',
            'description' => 'A test voucher',
            'voucher_type' => 'discount_amount',
            'discount_amount' => 50,
            'discount_percent' => null,
            'currency' => 'ZAR',
            'usage_limit' => 10,
            'start_at' => now()->addDay()->toIso8601String(),
            'end_at' => now()->addDays(10)->toIso8601String(),
            'terms' => 'One per customer',
            'status' => 'draft',
        ];

        $response = $this->actingAs($admin)->postJson(route('api.admin.vouchers.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('ok', true);

        $this->assertSame(1, Voucher::count());
        $this->assertSame($listing->id, Voucher::first()->listing_id);
    }

    public function test_admin_can_create_integration_via_admin_api(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $listing = Listing::factory()->create();

        $payload = [
            'listing_id' => $listing->id,
            'type' => 'google_analytics',
            'provider' => 'ga4',
            'status' => 'active',
            'settings_text' => '{"measurement_id":"G-TEST"}',
        ];

        $response = $this->actingAs($admin)->postJson(route('api.admin.integrations.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('ok', true);

        $this->assertSame(1, MarketingIntegration::count());
        $this->assertSame('active', MarketingIntegration::first()->status);
    }
}
