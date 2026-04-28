<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_detail_page_renders_core_profile_sections(): void
    {
        $owner = User::factory()->create(['role' => 'business_owner']);
        $packageType = PackageType::create([
            'name' => 'Directory',
            'slug' => 'directory',
            'description' => 'Directory access',
        ]);
        $package = Package::create([
            'package_type_id' => $packageType->id,
            'name' => 'Directory Package',
            'slug' => 'directory-package',
            'description' => 'Directory package',
            'billing_model' => 'recurring',
            'is_self_service' => true,
            'duration_days' => 30,
            'status' => 'active',
            'settings_json' => ['entitlement_code' => 'business_directory'],
        ]);

        $listing = Listing::create([
            'user_id' => $owner->id,
            'title' => 'Blue Crane Bakery',
            'slug' => 'blue-crane-bakery',
            'status' => 'published',
            'excerpt' => 'Fresh local bakery and cafe.',
            'description' => 'Blue Crane Bakery serves breads, pastries, and coffee for the local community.',
            'city' => 'Bethlehem',
            'region' => 'Free State',
            'phone' => '058 000 0000',
            'email' => 'hello@example.com',
            'address_line' => '12 Muller Street',
            'website_url' => 'https://example.com',
        ]);

        $subscription = Subscription::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'subscribable_type' => Listing::class,
            'subscribable_id' => $listing->id,
            'status' => 'active',
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->addMonth(),
            'renewal_mode' => 'manual',
        ]);

        $listing->update([
            'active_subscription_id' => $subscription->id,
        ]);

        $response = $this->get(route('directory.show', $listing));

        $response->assertOk();
        $response->assertSee('Blue Crane Bakery');
        $response->assertSee('About This Business');
        $response->assertSee('Contact and Location');
        $response->assertSee('Reviews');
        $response->assertSee('Upcoming Events');
        $response->assertSee('Related Listings');
    }
}
