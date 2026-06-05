<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackagePricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_price_change_requires_authority_note_and_creates_a_new_price_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = Package::where('slug', 'business-directory-standard-6m')->firstOrFail();
        $originalPrice = $package->currentPrice();

        $this->assertNotNull($originalPrice);
        $this->assertSame('500.00', number_format((float) $originalPrice->amount, 2, '.', ''));

        $payload = $this->packagePayload($package, [
            'amount' => '625.00',
            'currency' => 'ZAR',
            'vat_inclusive' => '1',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.packages.update', $package), $payload)
            ->assertSessionHasErrors('price_change_note');

        $this->assertSame(1, $package->prices()->count());
        $this->assertSame('500.00', number_format((float) $package->fresh()->currentPrice()->amount, 2, '.', ''));

        $this->actingAs($admin)
            ->put(route('admin.packages.update', $package), $payload + [
                'price_change_note' => 'Approved Q3 package pricing update.',
            ])
            ->assertRedirect(route('admin.packages.edit', $package));

        $package->refresh();
        $newPrice = $package->currentPrice();

        $this->assertSame(2, $package->prices()->count());
        $this->assertNotSame($originalPrice->id, $newPrice->id);
        $this->assertNotNull($originalPrice->fresh()->effective_to);
        $this->assertSame('625.00', number_format((float) $newPrice->amount, 2, '.', ''));
        $this->assertSame($admin->id, $newPrice->created_by_user_id);

        $audit = AuditLog::where('action', 'package_price.versioned')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame($newPrice->id, $audit->subject_id);
        $this->assertSame('500.00', $audit->before_json['amount']);
        $this->assertSame('625.00', $audit->after_json['amount']);
        $this->assertSame('Approved Q3 package pricing update.', $audit->after_json['change_note']);
        $this->assertSame($originalPrice->id, $audit->after_json['replaces_package_price_id']);
    }

    public function test_add_listing_public_price_uses_package_price_not_legacy_pricing_setting(): void
    {
        Setting::where('key', 'pricing.business_directory_6m')->update([
            'value' => '9999.00',
        ]);

        $response = $this->get(route('add-listing.index'));

        $response->assertOk();
        $response->assertSee('R500', false);
        $response->assertSee('R750', false);
        $response->assertDontSee('R9,999', false);
    }

    private function packagePayload(Package $package, array $overrides = []): array
    {
        return array_merge([
            'package_type_id' => $package->package_type_id,
            'name' => $package->name,
            'slug' => $package->slug,
            'description' => $package->description,
            'billing_model' => $package->billing_model,
            'is_self_service' => $package->is_self_service ? '1' : '0',
            'duration_days' => $package->duration_days,
            'status' => $package->status,
            'amount' => number_format((float) $package->currentPrice()->amount, 2, '.', ''),
            'currency' => $package->currentPrice()->currency,
            'vat_inclusive' => $package->currentPrice()->vat_inclusive ? '1' : '0',
        ], $overrides);
    }
}
