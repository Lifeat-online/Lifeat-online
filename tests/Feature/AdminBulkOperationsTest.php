<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Councillor;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_publish_listings_and_audit_is_recorded(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $listingA = Listing::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);
        $listingB = Listing::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.listings.bulk'), [
            'action' => 'publish',
            'ids' => [$listingA->slug, $listingB->slug],
        ]);

        $response->assertRedirect(route('admin.listings.index'));

        $this->assertSame('published', $listingA->fresh()->status);
        $this->assertNotNull($listingA->fresh()->published_at);
        $this->assertSame('published', $listingB->fresh()->status);

        $this->assertSame(2, AuditLog::where('action', 'listing.bulk_publish')->count());
    }

    public function test_admin_can_bulk_deactivate_councillors(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $c1 = Councillor::create([
            'full_name' => 'Councillor One',
            'phone' => null,
            'email' => null,
            'office_address' => null,
            'portfolios' => [],
            'category_responsibilities' => [],
            'is_active' => true,
        ]);
        $c2 = Councillor::create([
            'full_name' => 'Councillor Two',
            'phone' => null,
            'email' => null,
            'office_address' => null,
            'portfolios' => [],
            'category_responsibilities' => [],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.councillors.bulk'), [
            'action' => 'deactivate',
            'ids' => [$c1->id, $c2->id],
        ]);

        $response->assertRedirect(route('admin.councillors.index'));
        $this->assertFalse((bool) $c1->fresh()->is_active);
        $this->assertFalse((bool) $c2->fresh()->is_active);
    }
}
