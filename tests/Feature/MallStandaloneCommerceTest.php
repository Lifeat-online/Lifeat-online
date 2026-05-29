<?php

namespace Tests\Feature;

use App\Mail\MallOrderConfirmationMail;
use App\Events\TransportRequestOffered;
use App\Events\TransportRequestStatusChanged;
use App\Models\MallCart;
use App\Models\MallOrder;
use App\Models\MallPayment;
use App\Models\MallProduct;
use App\Models\MallProductCategory;
use App\Models\MallStore;
use App\Models\MallStoreCategory;
use App\Models\MallVendorProfile;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use App\Models\TransportRequestOffer;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Services\MallPayFastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MallStandaloneCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mall_home_is_a_window_shopping_corridor(): void
    {
        [$store, $product] = $this->createStoreWithProduct(price: '50.00', stock: 5);

        $response = $this->get(route('mall.index'));

        $response->assertOk();
        $response->assertSee('Walk the mall corridor');
        $response->assertSee($store->name);
        $response->assertSee($product->name);
        $response->assertSee('Enter Store');
        $response->assertDontSee('View Storefront');
        $response->assertSee('mall-window-shelf', false);
    }

    public function test_store_window_keeps_products_inside_the_storefront_block(): void
    {
        [$store, $product] = $this->createStoreWithProduct(price: '50.00', stock: 5);

        $response = $this->get(route('mall.stores.window', $store));

        $response->assertOk();
        $response->assertSee('mall-storefront-body', false);
        $response->assertSee('mall-window-strip', false);
        $response->assertSee($product->name);
        $response->assertSee('Enter Store');
        $response->assertDontSee('Window picks');
    }

    public function test_guest_cart_is_store_scoped_and_snapshots_price_without_touching_stock(): void
    {
        [$store, $product] = $this->createStoreWithProduct(price: '50.00', stock: 5);

        $this->post(route('mall.cart.items.store', [$store, $product]), [
            'quantity' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('mall_cart_items', [
            'mall_product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '50.00',
        ]);

        $this->assertSame(5, $product->fresh()->stock_qty);

        $product->update(['price' => '65.00']);

        $this->assertDatabaseHas('mall_cart_items', [
            'mall_product_id' => $product->id,
            'unit_price' => '50.00',
        ]);
    }

    public function test_guest_mall_cart_merges_on_login(): void
    {
        [$store, $product] = $this->createStoreWithProduct();
        $user = User::factory()->create();

        $this->post(route('mall.cart.items.store', [$store, $product]), [
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('mall_carts', [
            'user_id' => null,
            'mall_store_id' => $store->id,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('mall_carts', [
            'user_id' => $user->id,
            'mall_store_id' => $store->id,
            'session_token' => null,
        ]);
        $this->assertDatabaseMissing('mall_carts', [
            'user_id' => null,
            'mall_store_id' => $store->id,
        ]);
    }

    public function test_mall_checkout_creates_only_mall_order_and_payment(): void
    {
        [$store, $product] = $this->createStoreWithProduct(price: '120.00', stock: 4);
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('mall.cart.items.store', [$store, $product]), [
            'quantity' => 2,
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('mall.checkout.initiate', $store), [
            'notes' => 'Please pack carefully.',
        ])->assertOk()->assertSee('Redirecting to PayFast');

        $this->assertDatabaseCount('mall_orders', 1);
        $this->assertDatabaseCount('mall_payments', 1);
        $this->assertDatabaseCount('mall_fulfillments', 1);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        $order = MallOrder::firstOrFail();
        $this->assertSame('240.00', $order->total);
        $this->assertSame('24.00', $order->platform_fee);
        $this->assertSame('216.00', $order->vendor_amount);
        $this->assertSame('pickup', $order->fulfillment->provider);
        $this->assertSame('0.00', $order->fulfillment->delivery_fee);
    }

    public function test_taxi_delivery_adds_delivery_fee_and_separate_platform_cut(): void
    {
        [$store, $product] = $this->createStoreWithProduct(price: '120.00', stock: 4);
        $customer = User::factory()->create();
        $this->createActiveTransportDeliveryDriver(baseFee: 10, perKmFee: 6, minimumFee: 25);

        $this->actingAs($customer)->post(route('mall.cart.items.store', [$store, $product]), [
            'quantity' => 1,
        ])->assertRedirect();

        $this->actingAs($customer)->get(route('mall.checkout.show', $store))
            ->assertOk()
            ->assertSee('Delivery address or PUDO locker details')
            ->assertSee('Locate Me')
            ->assertSee('value="bicycle"', false)
            ->assertDontSee('value="ldv"', false);

        $this->actingAs($customer)
            ->from(route('mall.checkout.show', $store))
            ->post(route('mall.checkout.initiate', $store), [
                'delivery_area' => 'local',
                'delivery_method' => 'taxi',
                'required_vehicle_type' => 'ldv',
                'delivery_address' => '12 Local Street',
                'delivery_latitude' => '-28.2319',
                'delivery_longitude' => '28.3501',
                'contact_phone' => '0820000000',
            ])
            ->assertRedirect(route('mall.checkout.show', $store))
            ->assertSessionHasErrors('required_vehicle_type');

        $this->actingAs($customer)->post(route('mall.checkout.initiate', $store), [
            'delivery_area' => 'local',
            'delivery_method' => 'taxi',
            'required_vehicle_type' => 'bicycle',
            'delivery_address' => '12 Local Street',
            'delivery_latitude' => '-28.2319',
            'delivery_longitude' => '28.3501',
            'contact_phone' => '0820000000',
        ])->assertOk()->assertSee('Redirecting to PayFast');

        $order = MallOrder::with('fulfillment')->firstOrFail();
        $this->assertSame('154.00', $order->total);
        $this->assertSame('15.40', $order->platform_fee);
        $this->assertSame('108.00', $order->vendor_amount);
        $this->assertSame('taxi', $order->fulfillment->provider);
        $this->assertSame('34.00', $order->fulfillment->delivery_fee);
        $this->assertSame('3.40', $order->fulfillment->platform_fee);
        $this->assertSame('30.60', $order->fulfillment->provider_amount);
        $this->assertSame(4.0, (float) $order->fulfillment->meta['transport_quote']['delivery_distance_km']);
        $this->assertSame(2.0, (float) $order->fulfillment->meta['transport_quote']['parcel_weight_kg']);
        $this->assertEqualsWithDelta(-28.2319, (float) $order->fulfillment->meta['pickup_latitude'], 0.0001);
        $this->assertEqualsWithDelta(28.3501, (float) $order->fulfillment->meta['delivery_longitude'], 0.0001);
        $this->assertSame('2.000', $order->items()->firstOrFail()->parcel_weight_kg);
    }

    public function test_paid_taxi_delivery_creates_normal_transport_driver_offers(): void
    {
        Mail::fake();
        Event::fake([
            TransportRequestOffered::class,
            TransportRequestStatusChanged::class,
        ]);

        [$store, $product] = $this->createStoreWithProduct(price: '80.00', stock: 3);
        $driver = $this->createActiveTransportDeliveryDriver(baseFee: 10, perKmFee: 6, minimumFee: 25);
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('mall.cart.items.store', [$store, $product]), [
            'quantity' => 1,
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('mall.checkout.initiate', $store), [
            'delivery_area' => 'local',
            'delivery_method' => 'taxi',
            'delivery_address' => '12 Local Street',
            'delivery_latitude' => '-28.2319',
            'delivery_longitude' => '28.3501',
            'contact_phone' => '0820000000',
        ])->assertOk();

        $order = MallOrder::with('payments', 'fulfillment')->firstOrFail();
        $payload = [
            'm_payment_id' => $order->order_number,
            'pf_payment_id' => 'pf-mall-taxi-123',
            'payment_status' => 'COMPLETE',
            'item_name' => 'Life Mall Order',
            'amount_gross' => '114.00',
            'amount_fee' => '3.00',
            'amount_net' => '111.00',
        ];
        $payload['signature'] = app(MallPayFastService::class)->generateSignature($payload);

        $this->post(route('mall.payment.itn'), $payload)->assertOk();

        $transportRequest = TransportRequest::firstOrFail();
        $offer = TransportRequestOffer::firstOrFail();

        $this->assertSame('parcel', $transportRequest->service_type);
        $this->assertSame($store->pickup_address, $transportRequest->pickup_address);
        $this->assertSame('12 Local Street', $transportRequest->dropoff_address);
        $this->assertEqualsWithDelta(-28.2319, (float) $transportRequest->pickup_latitude, 0.0001);
        $this->assertEqualsWithDelta(28.3501, (float) $transportRequest->dropoff_longitude, 0.0001);
        $this->assertSame(4.0, (float) $transportRequest->distance_km);
        $this->assertSame(34.0, (float) $transportRequest->quoted_amount);
        $this->assertSame($driver->id, $offer->transport_driver_id);
        $this->assertSame(3.4, (float) $offer->platform_fee);
        $this->assertSame('transport_request', $order->fulfillment->fresh()->external_type);
        $this->assertSame($transportRequest->id, $order->fulfillment->fresh()->external_id);
        Event::assertDispatched(TransportRequestOffered::class);
        Event::assertDispatched(TransportRequestStatusChanged::class);
    }

    public function test_pudo_delivery_is_available_for_non_local_orders(): void
    {
        config(['mall.pudo.api_key' => 'test-pudo-key']);
        Http::fake([
            'https://api-sandbox.pudo.co.za/rates' => Http::response([
                'rates' => [[
                    'rate' => '82.50',
                    'charged_weight' => 2,
                    'rate_revision_id' => 942,
                    'service_level' => [
                        'code' => 'D2LXS - ECO',
                        'name' => 'Door to Locker Extra Small',
                    ],
                ]],
            ]),
            'https://api-sandbox.pudo.co.za/shipments' => Http::response([
                'id' => 6870626,
                'status' => 'deposit-pending',
                'custom_tracking_reference' => 'TCGD000503',
                'pincode' => '633857',
                'service_level_code' => 'D2LXS - ECO',
                'service_level_name' => 'Door to Locker Extra Small',
            ]),
        ]);
        Mail::fake();

        [$store, $product] = $this->createStoreWithProduct(price: '100.00', stock: 4);
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('mall.cart.items.store', [$store, $product]), [
            'quantity' => 1,
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('mall.checkout.initiate', $store), [
            'delivery_area' => 'non_local',
            'delivery_method' => 'pudo',
            'delivery_address' => 'PUDO Locker: Bloemfontein',
            'pudo_locker_code' => 'CG341',
            'pudo_locker_name' => 'Bloemfontein PUDO',
            'pudo_locker_latitude' => '-29.1187',
            'pudo_locker_longitude' => '26.2249',
            'contact_phone' => '0820000000',
        ])->assertOk()->assertSee('Redirecting to PayFast');

        $order = MallOrder::with('fulfillment')->firstOrFail();
        $this->assertSame('182.50', $order->total);
        $this->assertSame('10.00', $order->platform_fee);
        $this->assertSame('90.00', $order->vendor_amount);
        $this->assertSame('pudo', $order->fulfillment->provider);
        $this->assertSame('82.50', $order->fulfillment->delivery_fee);
        $this->assertSame('0.00', $order->fulfillment->platform_fee);
        $this->assertSame('CG341', $order->fulfillment->meta['pudo_quote']['locker_code']);
        $this->assertSame('D2LXS - ECO', $order->fulfillment->meta['pudo_quote']['service_level_code']);

        $payload = [
            'm_payment_id' => $order->order_number,
            'pf_payment_id' => 'pf-mall-pudo-123',
            'payment_status' => 'COMPLETE',
            'item_name' => 'Life Mall Order',
            'amount_gross' => '182.50',
            'amount_fee' => '3.00',
            'amount_net' => '179.50',
        ];
        $payload['signature'] = app(MallPayFastService::class)->generateSignature($payload);

        $this->post(route('mall.payment.itn'), $payload)->assertOk();

        $fulfillment = $order->fulfillment->fresh();
        $this->assertSame('pudo_shipment', $fulfillment->external_type);
        $this->assertSame(6870626, (int) $fulfillment->external_id);
        $this->assertSame('deposit-pending', $fulfillment->status);
        $this->assertSame('TCGD000503', $fulfillment->meta['pudo_shipment']['custom_tracking_reference']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.pudo.co.za/rates'
            && $request->method() === 'POST'
            && $request['delivery_address']['terminal_id'] === 'CG341');
        Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.pudo.co.za/shipments'
            && $request->method() === 'POST'
            && $request['service_level_code'] === 'D2LXS - ECO');
    }

    public function test_payfast_itn_marks_mall_order_paid_decrements_stock_and_keeps_existing_payments_empty(): void
    {
        Mail::fake();

        [$store, $product] = $this->createStoreWithProduct(price: '80.00', stock: 3);
        MallVendorProfile::create([
            'mall_store_id' => $store->id,
            'user_id' => $store->owner_user_id,
            'contact_name' => 'Vendor',
            'contact_email' => 'vendor@example.com',
        ]);
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('mall.cart.items.store', [$store, $product]), [
            'quantity' => 2,
        ])->assertRedirect();

        $this->actingAs($customer)->post(route('mall.checkout.initiate', $store), [
            'delivery_area' => 'local',
            'delivery_method' => 'pickup',
        ])->assertOk();

        $order = MallOrder::with('payments')->firstOrFail();
        $payload = [
            'm_payment_id' => $order->order_number,
            'pf_payment_id' => 'pf-mall-123',
            'payment_status' => 'COMPLETE',
            'item_name' => 'Life Mall Order',
            'amount_gross' => '160.00',
            'amount_fee' => '5.00',
            'amount_net' => '155.00',
        ];
        $payload['signature'] = app(MallPayFastService::class)->generateSignature($payload);

        $this->post(route('mall.payment.itn'), $payload)->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('complete', MallPayment::firstOrFail()->status);
        $this->assertSame(1, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('mall_carts', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('orders', 0);
        Mail::assertQueued(MallOrderConfirmationMail::class);
    }

    public function test_mall_admin_can_update_store_and_product_controls(): void
    {
        [$store, $product] = $this->createStoreWithProduct(price: '80.00', stock: 3);
        $storeCategory = MallStoreCategory::create([
            'name' => 'Gifts',
            'slug' => 'gifts',
        ]);
        $productCategory = MallProductCategory::create([
            'mall_store_id' => $store->id,
            'name' => 'Pantry',
            'slug' => 'pantry',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('mall.admin.stores.edit', $store))
            ->assertOk()
            ->assertSee('Pickup Point')
            ->assertSee('admin_pickup_address', false);

        $this->actingAs($admin)->put(route('mall.admin.stores.update', $store), [
            'name' => 'Updated Store',
            'tagline' => 'Fresh admin copy',
            'description' => 'Admin managed store description.',
            'pickup_address' => 'Updated pickup point, Bethlehem',
            'pickup_latitude' => '-28.2322',
            'pickup_longitude' => '28.3101',
            'primary_color' => '#22C55E',
            'status' => 'active',
            'is_featured' => '1',
            'category_ids' => [$storeCategory->id],
        ])->assertRedirect(route('mall.admin.stores.show', $store));

        $this->actingAs($admin)->put(route('mall.admin.products.update', $product->id), [
            'name' => 'Updated Product',
            'price' => '95.00',
            'compare_price' => '120.00',
            'sku' => 'UPD-1',
            'stock_qty' => 9,
            'parcel_weight_kg' => '1.250',
            'short_description' => 'Admin product copy.',
            'description' => 'Full product copy.',
            'manage_stock' => '1',
            'is_featured' => '1',
            'is_active' => '0',
            'category_ids' => [$productCategory->id],
        ])->assertRedirect(route('mall.admin.products.index'));

        $this->assertDatabaseHas('mall_stores', [
            'id' => $store->id,
            'name' => 'Updated Store',
            'status' => 'active',
            'is_featured' => true,
            'pickup_address' => 'Updated pickup point, Bethlehem',
        ]);
        $store->refresh();
        $this->assertSame('-28.2322000', $store->pickup_latitude);
        $this->assertSame('28.3101000', $store->pickup_longitude);
        $this->assertDatabaseHas('mall_products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'price' => '95.00',
            'stock_qty' => 9,
            'parcel_weight_kg' => '1.250',
            'is_featured' => true,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('mall_store_category_mall_store', [
            'mall_store_id' => $store->id,
            'mall_store_category_id' => $storeCategory->id,
        ]);
        $this->assertDatabaseHas('mall_product_mall_product_category', [
            'mall_product_id' => $product->id,
            'mall_product_category_id' => $productCategory->id,
        ]);
    }

    private function createStoreWithProduct(string $price = '25.00', int $stock = 10): array
    {
        $owner = User::factory()->create();
        $store = MallStore::create([
            'owner_user_id' => $owner->id,
            'name' => 'Test Store',
            'slug' => 'test-store-'.fake()->unique()->numberBetween(1000, 9999),
            'tagline' => 'Standalone mall store',
            'pickup_address' => 'Test Store pickup point, Bethlehem, Free State',
            'pickup_latitude' => -28.2319,
            'pickup_longitude' => 28.3093,
            'status' => 'active',
            'is_featured' => true,
        ]);
        $product = MallProduct::create([
            'mall_store_id' => $store->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => $price,
            'stock_qty' => $stock,
            'parcel_weight_kg' => '2.000',
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
        ]);

        return [$store, $product];
    }

    private function createActiveTransportDeliveryDriver(float $baseFee, float $perKmFee, float $minimumFee): TransportDriver
    {
        $driverUser = User::factory()->create(['role' => 'transport_driver']);
        $driver = TransportDriver::create([
            'user_id' => $driverUser->id,
            'status' => TransportDriver::STATUS_APPROVED,
            'can_transport_parcels' => true,
            'approved_at' => now(),
        ]);
        $vehicle = TransportVehicle::create([
            'transport_driver_id' => $driver->id,
            'name' => 'Approved bicycle',
            'vehicle_type' => 'bicycle',
            'status' => TransportVehicle::STATUS_APPROVED,
            'can_carry_parcels' => true,
            'max_weight_kg' => 15,
            'pricing_mode' => 'per_km',
            'base_fee' => $baseFee,
            'per_km_fee' => $perKmFee,
            'minimum_fee' => $minimumFee,
            'accepts_payfast' => true,
            'approved_at' => now(),
        ]);
        TransportDutySession::create([
            'transport_driver_id' => $driver->id,
            'transport_vehicle_id' => $vehicle->id,
            'status' => TransportDutySession::STATUS_AVAILABLE,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        return $driver;
    }
}
