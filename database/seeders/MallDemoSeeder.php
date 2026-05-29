<?php

namespace Database\Seeders;

use App\Models\MallProduct;
use App\Models\MallProductCategory;
use App\Models\MallStore;
use App\Models\MallStoreCategory;
use App\Models\MallVendorProfile;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MallDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MallStoreCategorySeeder::class);

        $owner = User::updateOrCreate(
            ['email' => 'mall.vendor@example.com'],
            [
                'name' => 'Mall Demo Vendor',
                'password' => Hash::make('password'),
                'role' => 'business_owner',
                'email_verified_at' => now(),
            ]
        );

        $customer = User::updateOrCreate(
            ['email' => 'mall.customer@example.com'],
            [
                'name' => 'Mall Demo Customer',
                'password' => Hash::make('password'),
                'role' => 'registered_user',
                'email_verified_at' => now(),
            ]
        );

        $admin = User::updateOrCreate(
            ['email' => 'mall.admin@example.com'],
            [
                'name' => 'Mall Demo Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $driverUser = User::updateOrCreate(
            ['email' => 'mall.driver@example.com'],
            [
                'name' => 'Mall Taxi Driver',
                'password' => Hash::make('password'),
                'role' => 'transport_driver',
                'email_verified_at' => now(),
            ]
        );

        $driver = TransportDriver::updateOrCreate(
            ['user_id' => $driverUser->id],
            [
                'status' => TransportDriver::STATUS_APPROVED,
                'phone' => '0580000001',
                'can_transport_parcels' => true,
                'approved_at' => now(),
                'approved_by_user_id' => $admin->id,
            ]
        );

        $vehicle = TransportVehicle::updateOrCreate(
            ['transport_driver_id' => $driver->id, 'name' => 'Mall Parcel Bike'],
            [
                'manager_user_id' => $admin->id,
                'vehicle_type' => 'bicycle',
                'status' => TransportVehicle::STATUS_APPROVED,
                'can_carry_parcels' => true,
                'max_weight_kg' => 15,
                'pricing_mode' => 'per_km',
                'base_fee' => '10.00',
                'per_km_fee' => '6.00',
                'minimum_fee' => '25.00',
                'accepts_payfast' => true,
                'approved_at' => now(),
                'approved_by_user_id' => $admin->id,
            ]
        );

        TransportDutySession::updateOrCreate(
            ['transport_driver_id' => $driver->id, 'ended_at' => null],
            [
                'transport_vehicle_id' => $vehicle->id,
                'status' => TransportDutySession::STATUS_AVAILABLE,
                'started_at' => now(),
                'last_latitude' => -28.2319,
                'last_longitude' => 28.3093,
                'last_seen_at' => now(),
            ]
        );

        $store = MallStore::updateOrCreate(
            ['slug' => 'life-market-demo'],
            [
                'owner_user_id' => $owner->id,
                'name' => 'Life Market Demo',
                'tagline' => 'Local favourites, gifts, and pantry finds.',
                'description' => 'A demo mall storefront for testing store browsing, per-store basket behavior, and PayFast checkout handoff.',
                'pickup_address' => 'Life Market Demo pickup point, Bethlehem, Free State',
                'pickup_latitude' => -28.2319,
                'pickup_longitude' => 28.3093,
                'primary_color' => '#0F766E',
                'payfast_merchant_id' => '10000100',
                'payfast_merchant_key' => '46f0cd694581a',
                'status' => 'active',
                'is_featured' => true,
            ]
        );

        $storeCategories = MallStoreCategory::whereIn('slug', ['food', 'home', 'services'])->pluck('id');
        $store->categories()->sync($storeCategories);

        MallVendorProfile::updateOrCreate(
            ['mall_store_id' => $store->id],
            [
                'user_id' => $owner->id,
                'contact_name' => 'Mall Demo Vendor',
                'contact_email' => 'mall.vendor@example.com',
                'contact_phone' => '0580000000',
                'business_reg' => 'DEMO-2026',
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]
        );

        $giftCategory = MallProductCategory::updateOrCreate(
            ['mall_store_id' => $store->id, 'slug' => 'gifts'],
            ['name' => 'Gifts', 'sort_order' => 1]
        );

        $pantryCategory = MallProductCategory::updateOrCreate(
            ['mall_store_id' => $store->id, 'slug' => 'pantry'],
            ['name' => 'Pantry', 'sort_order' => 2]
        );

        $homeCategory = MallProductCategory::updateOrCreate(
            ['mall_store_id' => $store->id, 'slug' => 'home'],
            ['name' => 'Home', 'sort_order' => 3]
        );

        $products = [
            [
                'slug' => 'artisan-coffee-box',
                'name' => 'Artisan Coffee Box',
                'short_description' => 'Small-batch beans with a tasting card.',
                'description' => 'A polished demo product with managed stock and a price snapshot path for cart testing.',
                'price' => '149.00',
                'compare_price' => '179.00',
                'sku' => 'LM-COFFEE',
                'stock_qty' => 18,
                'parcel_weight_kg' => '1.200',
                'is_featured' => true,
                'categories' => [$giftCategory->id, $pantryCategory->id],
            ],
            [
                'slug' => 'freestate-honey-jar',
                'name' => 'Freestate Honey Jar',
                'short_description' => 'Raw local honey in a glass jar.',
                'description' => 'A pantry item for checking product listing, detail pages, and quantity changes.',
                'price' => '79.00',
                'compare_price' => null,
                'sku' => 'LM-HONEY',
                'stock_qty' => 24,
                'parcel_weight_kg' => '0.650',
                'is_featured' => true,
                'categories' => [$pantryCategory->id],
            ],
            [
                'slug' => 'woven-table-runner',
                'name' => 'Woven Table Runner',
                'short_description' => 'Textured table runner for everyday dining.',
                'description' => 'A homeware product to exercise category filtering and store catalog display.',
                'price' => '229.00',
                'compare_price' => null,
                'sku' => 'LM-RUNNER',
                'stock_qty' => 8,
                'parcel_weight_kg' => '0.900',
                'is_featured' => true,
                'categories' => [$homeCategory->id, $giftCategory->id],
            ],
            [
                'slug' => 'market-gift-card',
                'name' => 'Market Gift Card',
                'short_description' => 'A flexible gift card for the demo shop.',
                'description' => 'Stock is unmanaged to test always-available product behavior.',
                'price' => '250.00',
                'compare_price' => null,
                'sku' => 'LM-GIFTCARD',
                'stock_qty' => 0,
                'parcel_weight_kg' => '0.050',
                'manage_stock' => false,
                'is_featured' => true,
                'categories' => [$giftCategory->id],
            ],
            [
                'slug' => 'ceramic-breakfast-bowl',
                'name' => 'Ceramic Breakfast Bowl',
                'short_description' => 'Simple hand-finished ceramic bowl.',
                'description' => 'A non-featured product for full catalog scrolling and sort tests.',
                'price' => '119.00',
                'compare_price' => null,
                'sku' => 'LM-BOWL',
                'stock_qty' => 12,
                'parcel_weight_kg' => '0.800',
                'is_featured' => false,
                'categories' => [$homeCategory->id],
            ],
            [
                'slug' => 'picnic-snack-pack',
                'name' => 'Picnic Snack Pack',
                'short_description' => 'Local snacks ready for a weekend picnic.',
                'description' => 'A sixth product so the storefront window can show a full featured set.',
                'price' => '99.00',
                'compare_price' => '129.00',
                'sku' => 'LM-SNACKS',
                'stock_qty' => 16,
                'parcel_weight_kg' => '0.700',
                'is_featured' => true,
                'categories' => [$pantryCategory->id, $giftCategory->id],
            ],
        ];

        foreach ($products as $productData) {
            $categories = $productData['categories'];
            unset($productData['categories']);

            $product = MallProduct::updateOrCreate(
                ['mall_store_id' => $store->id, 'slug' => $productData['slug']],
                array_merge([
                    'mall_store_id' => $store->id,
                    'manage_stock' => true,
                    'is_active' => true,
                    'images' => null,
                ], $productData)
            );

            $product->categories()->sync($categories);
        }

        $this->command?->info('Mall demo users: mall.admin@example.com, mall.vendor@example.com, mall.customer@example.com, mall.driver@example.com / password');
    }
}
