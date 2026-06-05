<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Package;
use App\Models\PackageType;
use App\Models\Setting;
use App\Support\Caching\PublicReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicReadCacheStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'lifeat_cache.settings_ttl' => 3600,
            'lifeat_cache.catalog_ttl' => 3600,
            'lifeat_cache.public_ttl' => 3600,
        ]);
    }

    public function test_setting_cache_is_invalidated_when_settings_change(): void
    {
        $setting = Setting::create([
            'key' => 'cache.test.setting',
            'value' => '500.00',
            'type' => 'decimal',
            'group' => 'pricing',
        ]);

        $this->assertSame('500.00', Setting::getValue('cache.test.setting'));

        $setting->update(['value' => '650.00']);

        $this->assertSame('650.00', Setting::getValue('cache.test.setting'));
    }

    public function test_package_catalogue_cache_is_invalidated_when_prices_change(): void
    {
        $type = PackageType::create([
            'name' => 'Cache Test Catalogue',
            'slug' => 'cache_test_catalogue',
        ]);

        $package = Package::create([
            'package_type_id' => $type->id,
            'name' => 'Directory Standard',
            'slug' => 'directory-standard',
            'description' => 'Directory package',
            'billing_model' => 'six_monthly',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $price = $package->prices()->create([
            'currency' => 'ZAR',
            'amount' => 500,
            'vat_inclusive' => true,
            'effective_from' => now()->subDay(),
        ]);

        $this->assertSame(500.0, PublicReadCache::activePackagesForType('cache_test_catalogue')->first()['current_price']['amount']);

        $price->update(['amount' => 650]);

        $this->assertSame(650.0, PublicReadCache::activePackagesForType('cache_test_catalogue')->first()['current_price']['amount']);
    }

    public function test_public_category_cache_is_invalidated_when_reference_data_changes(): void
    {
        $category = Category::create([
            'type' => 'listing',
            'name' => 'Food',
            'slug' => 'food',
            'description' => 'Local food businesses',
        ]);

        $this->assertSame('Food', PublicReadCache::listingCategories()->firstWhere('slug', 'food')['name']);

        $category->update(['name' => 'Food And Drink']);

        $this->assertSame('Food And Drink', PublicReadCache::listingCategories()->firstWhere('slug', 'food')['name']);
    }

    public function test_public_pages_render_cached_catalogue_and_category_arrays(): void
    {
        $type = PackageType::firstOrCreate(
            ['slug' => 'business_directory'],
            ['name' => 'Business Directory']
        );

        $package = Package::create([
            'package_type_id' => $type->id,
            'name' => 'Directory Standard',
            'slug' => 'cache-test-directory-standard',
            'description' => 'Directory package',
            'billing_model' => 'six_monthly',
            'is_self_service' => false,
            'duration_days' => 180,
            'status' => 'active',
        ]);

        $package->prices()->create([
            'currency' => 'ZAR',
            'amount' => 500,
            'vat_inclusive' => true,
            'effective_from' => now()->subDay(),
        ]);

        Category::create([
            'type' => 'listing',
            'name' => 'Food',
            'slug' => 'food',
            'description' => 'Local food businesses',
        ]);

        $this->get(route('add-listing.index'))
            ->assertOk()
            ->assertSee('Directory Standard');

        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('Food');
    }
}
