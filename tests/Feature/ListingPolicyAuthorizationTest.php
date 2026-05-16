<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingPolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_listing_account_surface(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($owner)
            ->get(route('account.listings.edit', $listing))
            ->assertOk();
    }

    public function test_assigned_staff_can_manage_staff_assisted_listing_account_surface(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $staff = User::factory()->create(['role' => 'staff']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'registered_by_user_id' => $staff->id,
            'status' => 'draft',
        ]);

        $this->actingAs($staff)
            ->get(route('account.listings.edit', $listing))
            ->assertOk();
    }

    public function test_unassigned_user_cannot_manage_listing_account_surface(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $stranger = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($stranger)
            ->get(route('account.listings.edit', $listing))
            ->assertForbidden();
    }

    public function test_unrelated_user_cannot_start_checkout_for_listing(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $stranger = User::factory()->create(['role' => 'business_owner']);
        $listing = Listing::factory()->create([
            'user_id' => $owner->id,
            'status' => 'draft',
        ]);

        $this->actingAs($stranger)
            ->post(route('checkout.start'), [
                'package_slug' => 'business-directory-standard-6m',
                'listing_slug' => $listing->slug,
            ])
            ->assertForbidden();
    }
}
