<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddListingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_listing_page_renders_package_selection_and_starter_flow_copy(): void
    {
        $response = $this->get(route('add-listing.index'));

        $response->assertOk();
        $response->assertSee('Add Listing');
        $response->assertSee('Launch a polished local business listing in minutes.');
        $response->assertSee('Choose Your Listing Path');
        $response->assertSee('Business Directory Standard');
        $response->assertSee('Business Directory Self-Service');
        $response->assertSee('Start Your Listing');
    }

    public function test_authenticated_user_can_create_listing_starter_and_continue_to_checkout(): void
    {
        $user = User::factory()->create([
            'role' => 'business_owner',
        ]);

        $response = $this->actingAs($user)->post(route('add-listing.start'), [
            'title' => 'Golden Valley Grocer',
            'city' => 'Bethlehem',
            'package_slug' => 'business-directory-self-service-6m',
        ]);

        $listing = Listing::where('title', 'Golden Valley Grocer')->firstOrFail();

        $response->assertRedirect(route('checkout.index', [
            'listing' => $listing->slug,
            'package' => 'business-directory-self-service-6m',
        ]));

        $this->assertSame($user->id, $listing->user_id);
        $this->assertSame('self_service', $listing->source_channel);
        $this->assertSame('draft', $listing->status);
        $this->assertSame('Bethlehem', $listing->city);
    }
}
