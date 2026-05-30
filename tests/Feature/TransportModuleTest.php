<?php

namespace Tests\Feature;

use App\Models\TransportDriver;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use App\Models\TransportRequestOffer;
use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_navigation_links_to_taxi_and_delivery_feature_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Taxi / Delivery')
            ->assertSee(route('transport.index'), false);

        $this->get(route('transport.index'))
            ->assertOk()
            ->assertSee('Request taxi or delivery')
            ->assertSee(route('transport.requests.create'), false);
    }

    public function test_transport_page_shows_online_driver_map_and_statuses(): void
    {
        $availableUser = User::factory()->create(['name' => 'Available Driver', 'role' => 'transport_driver']);
        $availableDriver = TransportDriver::create([
            'user_id' => $availableUser->id,
            'status' => TransportDriver::STATUS_APPROVED,
            'can_transport_people' => true,
            'approved_at' => now(),
        ]);
        $availableVehicle = TransportVehicle::create([
            'transport_driver_id' => $availableDriver->id,
            'name' => 'Blue sedan',
            'vehicle_type' => 'car',
            'status' => TransportVehicle::STATUS_APPROVED,
            'can_carry_people' => true,
            'pricing_mode' => 'per_km',
            'base_fee' => 20,
            'per_km_fee' => 8,
            'minimum_fee' => 35,
            'approved_at' => now(),
        ]);
        TransportDutySession::create([
            'transport_driver_id' => $availableDriver->id,
            'transport_vehicle_id' => $availableVehicle->id,
            'status' => TransportDutySession::STATUS_AVAILABLE,
            'started_at' => now(),
            'last_latitude' => -28.2319,
            'last_longitude' => 28.3093,
            'last_seen_at' => now(),
        ]);

        $busyUser = User::factory()->create(['name' => 'Busy Driver', 'role' => 'transport_driver']);
        $busyDriver = TransportDriver::create([
            'user_id' => $busyUser->id,
            'status' => TransportDriver::STATUS_APPROVED,
            'can_transport_parcels' => true,
            'approved_at' => now(),
        ]);
        $busyVehicle = TransportVehicle::create([
            'transport_driver_id' => $busyDriver->id,
            'name' => 'Parcel bakkie',
            'vehicle_type' => 'bakkie',
            'status' => TransportVehicle::STATUS_APPROVED,
            'can_carry_parcels' => true,
            'pricing_mode' => 'per_km',
            'base_fee' => 30,
            'per_km_fee' => 10,
            'minimum_fee' => 50,
            'approved_at' => now(),
        ]);
        TransportDutySession::create([
            'transport_driver_id' => $busyDriver->id,
            'transport_vehicle_id' => $busyVehicle->id,
            'status' => TransportDutySession::STATUS_BUSY,
            'started_at' => now(),
            'last_latitude' => -28.2400,
            'last_longitude' => 28.3200,
            'last_seen_at' => now(),
        ]);

        $this->get(route('transport.index'))
            ->assertOk()
            ->assertSee('Live driver availability')
            ->assertSee('transport-driver-map', false)
            ->assertSee('Available Driver')
            ->assertSee('Blue sedan')
            ->assertSee('Available')
            ->assertSee('Busy Driver')
            ->assertSee('Parcel bakkie')
            ->assertSee('Occupied')
            ->assertSee('life-marker-status-available', false)
            ->assertSee('life-marker-status-busy', false);
    }

    public function test_admin_can_setup_transport_managers_and_platform_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dev.transport.setup'))
            ->assertOk()
            ->assertSee('Transport Dev Setup')
            ->assertSee('Create transport manager');

        $this->actingAs($admin)
            ->post(route('dev.transport.managers.store'), [
                'name' => 'Dispatch Manager',
                'email' => 'dispatch.manager@example.com',
                'phone' => '0820000001',
            ])
            ->assertRedirect(route('dev.transport.setup'))
            ->assertSessionHas('temporary_password');

        $manager = User::where('email', 'dispatch.manager@example.com')->firstOrFail();
        $this->assertTrue($manager->hasRole('transport_manager'));

        $this->actingAs($admin)
            ->put(route('dev.transport.settings.update'), [
                'platform_fee_percent' => '12.5',
                'dispatch_offer_limit' => '15',
                'default_search_radius_km' => '30',
                'safety_contact_phone' => '0800000000',
                'safety_contact_email' => 'safety@example.com',
                'panic_button_mode' => 'support_dispatch',
                'require_driver_id_number' => '1',
                'require_driver_license' => '1',
                'cash_enabled' => '1',
                'card_machine_enabled' => '1',
                'payfast_enabled' => '1',
            ])
            ->assertRedirect(route('dev.transport.setup'));

        $this->assertDatabaseHas('settings', [
            'key' => 'transport.platform_fee_percent',
            'value' => '12.5',
        ]);
    }

    public function test_transport_setup_can_grant_manager_access_to_existing_user_without_replacing_primary_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existing = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'existing.admin@example.com',
            'phone' => '0811111111',
        ]);

        $this->actingAs($admin)
            ->post(route('dev.transport.managers.store'), [
                'name' => 'Existing Admin',
                'email' => $existing->email,
                'phone' => '0822222222',
            ])
            ->assertRedirect(route('dev.transport.setup'))
            ->assertSessionHas('status', 'Transport manager access granted.');

        $existing->refresh();

        $this->assertSame('super_admin', $existing->role);
        $this->assertSame('Existing Admin', $existing->name);
        $this->assertSame('0822222222', $existing->phone);
        $this->assertTrue($existing->hasRole('transport_manager'));

        $this->actingAs($admin)
            ->get(route('dev.transport.setup'))
            ->assertOk()
            ->assertSee('existing.admin@example.com');
    }

    public function test_dev_owner_can_create_driver_without_transport_manager_primary_role(): void
    {
        $devOwner = User::factory()->create([
            'role' => 'member',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($devOwner)
            ->post(route('transport.manager.drivers.store'), [
                'name' => 'Dev Saved Driver',
                'email' => 'dev.saved.driver@example.com',
                'phone' => '0830000000',
                'status' => 'approved',
                'can_transport_parcels' => '1',
            ])
            ->assertRedirect(route('transport.manager.dashboard'));

        $driver = TransportDriver::with('user')->firstOrFail();

        $this->assertSame($devOwner->id, $driver->manager_user_id);
        $this->assertSame('Dev Saved Driver', $driver->user->name);
        $this->assertTrue($driver->isApproved());
    }

    public function test_transport_manager_can_create_driver_and_vehicle(): void
    {
        $manager = User::factory()->create(['role' => 'transport_manager']);

        $this->actingAs($manager)
            ->post(route('transport.manager.drivers.store'), [
                'name' => 'Thabo Driver',
                'email' => 'thabo.driver@example.com',
                'phone' => '0820000000',
                'status' => 'approved',
                'can_transport_parcels' => '1',
            ])
            ->assertRedirect(route('transport.manager.dashboard'));

        $driver = TransportDriver::with('user')->firstOrFail();

        $this->assertSame('transport_driver', $driver->user->role);
        $this->assertTrue($driver->isApproved());

        $this->actingAs($manager)
            ->post(route('transport.manager.vehicles.store'), [
                'transport_driver_id' => $driver->id,
                'name' => 'Parcel bicycle',
                'vehicle_type' => 'bicycle',
                'status' => 'approved',
                'can_carry_parcels' => '1',
                'pricing_mode' => 'per_km',
                'base_fee' => '10',
                'per_km_fee' => '5',
                'minimum_fee' => '20',
                'accepts_cash' => '1',
                'accepts_payfast' => '1',
            ])
            ->assertRedirect(route('transport.manager.dashboard'));

        $this->assertDatabaseHas('transport_vehicles', [
            'transport_driver_id' => $driver->id,
            'vehicle_type' => 'bicycle',
            'status' => 'approved',
        ]);
    }

    public function test_transport_manager_can_open_driver_list_and_update_a_driver(): void
    {
        $manager = User::factory()->create(['role' => 'transport_manager']);
        $driverUser = User::factory()->create([
            'name' => 'Original Driver',
            'email' => 'original.driver@example.com',
            'role' => 'transport_driver',
        ]);
        $driver = TransportDriver::create([
            'user_id' => $driverUser->id,
            'manager_user_id' => $manager->id,
            'status' => TransportDriver::STATUS_PENDING,
            'phone' => '0810000000',
            'can_transport_people' => false,
            'can_transport_parcels' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('transport.manager.dashboard'))
            ->assertOk()
            ->assertSee(route('transport.manager.drivers.index'), false)
            ->assertSee('Manage drivers')
            ->assertSee('<details id="add-driver"', false)
            ->assertDontSee('<details id="add-driver" class="rounded-lg bg-white p-6 shadow-sm" open', false);

        $this->actingAs($manager)
            ->get(route('transport.manager.dashboard', ['form' => 'driver']))
            ->assertOk()
            ->assertSee('<details id="add-driver" class="rounded-lg bg-white p-6 shadow-sm" open', false);

        $this->actingAs($manager)
            ->get(route('transport.manager.drivers.index'))
            ->assertOk()
            ->assertSee('Transport Drivers')
            ->assertSee('Original Driver')
            ->assertSee(route('transport.manager.drivers.edit', $driver), false);

        $this->actingAs($manager)
            ->get(route('transport.manager.drivers.edit', $driver))
            ->assertOk()
            ->assertSee('Edit Driver')
            ->assertSee('original.driver@example.com')
            ->assertSee(route('transport.manager.drivers.update', $driver), false);

        $this->actingAs($manager)
            ->put(route('transport.manager.drivers.update', $driver), [
                'name' => 'Updated Driver',
                'email' => 'updated.driver@example.com',
                'phone' => '0820000000',
                'id_number' => '8001015009087',
                'license_number' => 'LIC-123',
                'emergency_contact_name' => 'Emergency Person',
                'emergency_contact_phone' => '0830000000',
                'status' => 'approved',
                'can_transport_people' => '1',
                'notes' => 'Ready for airport trips.',
            ])
            ->assertRedirect(route('transport.manager.drivers.index'));

        $driver->refresh();
        $driverUser->refresh();

        $this->assertSame('Updated Driver', $driverUser->name);
        $this->assertSame('updated.driver@example.com', $driverUser->email);
        $this->assertSame('0820000000', $driverUser->phone);
        $this->assertTrue($driver->isApproved());
        $this->assertTrue($driver->can_transport_people);
        $this->assertFalse($driver->can_transport_parcels);
        $this->assertNotNull($driver->approved_at);
        $this->assertSame($manager->id, $driver->approved_by_user_id);
        $this->assertSame('Ready for airport trips.', $driver->notes);
    }

    public function test_transport_manager_can_open_vehicle_list_and_update_a_vehicle(): void
    {
        $manager = User::factory()->create(['role' => 'transport_manager']);
        $driverUser = User::factory()->create([
            'name' => 'Vehicle Driver',
            'email' => 'vehicle.driver@example.com',
            'role' => 'transport_driver',
        ]);
        $driver = TransportDriver::create([
            'user_id' => $driverUser->id,
            'manager_user_id' => $manager->id,
            'status' => TransportDriver::STATUS_APPROVED,
            'can_transport_parcels' => true,
            'approved_at' => now(),
        ]);
        $vehicle = TransportVehicle::create([
            'transport_driver_id' => $driver->id,
            'manager_user_id' => $manager->id,
            'name' => 'Old Scooter',
            'vehicle_type' => 'scooter',
            'registration_number' => 'OLD-123',
            'status' => TransportVehicle::STATUS_PENDING,
            'can_carry_parcels' => true,
            'pricing_mode' => 'per_km',
            'base_fee' => 10,
            'per_km_fee' => 5,
            'minimum_fee' => 25,
            'accepts_cash' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('transport.manager.dashboard'))
            ->assertOk()
            ->assertSee(route('transport.manager.vehicles.index'), false)
            ->assertSee('Manage vehicles')
            ->assertSee('<details id="add-vehicle"', false)
            ->assertDontSee('<details id="add-vehicle" class="rounded-lg bg-white p-6 shadow-sm" open', false);

        $this->actingAs($manager)
            ->get(route('transport.manager.dashboard', ['form' => 'vehicle']))
            ->assertOk()
            ->assertSee('<details id="add-vehicle" class="rounded-lg bg-white p-6 shadow-sm" open', false);

        $this->actingAs($manager)
            ->get(route('transport.manager.vehicles.index'))
            ->assertOk()
            ->assertSee('Transport Vehicles')
            ->assertSee('Old Scooter')
            ->assertSee(route('transport.manager.vehicles.edit', $vehicle), false);

        $this->actingAs($manager)
            ->get(route('transport.manager.vehicles.edit', $vehicle))
            ->assertOk()
            ->assertSee('Edit Vehicle')
            ->assertSee('Old Scooter')
            ->assertSee(route('transport.manager.vehicles.update', $vehicle), false);

        $this->actingAs($manager)
            ->put(route('transport.manager.vehicles.update', $vehicle), [
                'transport_driver_id' => $driver->id,
                'name' => 'Updated Bakkie',
                'vehicle_type' => 'bakkie',
                'registration_number' => 'NEW-456',
                'status' => 'approved',
                'can_carry_people' => '1',
                'can_carry_parcels' => '1',
                'max_passengers' => '3',
                'max_weight_kg' => '350.50',
                'pricing_mode' => 'per_km_plus_people',
                'base_fee' => '20',
                'per_km_fee' => '8',
                'per_person_fee' => '4',
                'minimum_fee' => '50',
                'waiting_fee' => '2',
                'cancellation_fee' => '15',
                'accepts_payfast' => '1',
                'notes' => 'Can handle larger parcel trips.',
            ])
            ->assertRedirect(route('transport.manager.vehicles.index'));

        $vehicle->refresh();

        $this->assertSame('Updated Bakkie', $vehicle->name);
        $this->assertSame('bakkie', $vehicle->vehicle_type);
        $this->assertSame('NEW-456', $vehicle->registration_number);
        $this->assertTrue($vehicle->isApproved());
        $this->assertTrue($vehicle->can_carry_people);
        $this->assertTrue($vehicle->can_carry_parcels);
        $this->assertFalse($vehicle->accepts_cash);
        $this->assertTrue($vehicle->accepts_payfast);
        $this->assertSame('per_km_plus_people', $vehicle->pricing_mode);
        $this->assertSame('20.00', $vehicle->base_fee);
        $this->assertSame('8.00', $vehicle->per_km_fee);
        $this->assertSame('4.00', $vehicle->per_person_fee);
        $this->assertSame('50.00', $vehicle->minimum_fee);
        $this->assertSame('2.00', $vehicle->waiting_fee);
        $this->assertSame('15.00', $vehicle->cancellation_fee);
        $this->assertNotNull($vehicle->approved_at);
        $this->assertSame($manager->id, $vehicle->approved_by_user_id);
        $this->assertSame('Can handle larger parcel trips.', $vehicle->notes);
    }

    public function test_driver_workspace_is_hidden_until_driver_clocks_in(): void
    {
        $user = User::factory()->create(['role' => 'transport_driver']);
        $driver = TransportDriver::create([
            'user_id' => $user->id,
            'status' => TransportDriver::STATUS_APPROVED,
            'can_transport_parcels' => true,
            'approved_at' => now(),
        ]);
        $vehicle = TransportVehicle::create([
            'transport_driver_id' => $driver->id,
            'name' => 'Small parcel bike',
            'vehicle_type' => 'bicycle',
            'status' => TransportVehicle::STATUS_APPROVED,
            'can_carry_parcels' => true,
            'pricing_mode' => 'per_km',
            'base_fee' => 10,
            'per_km_fee' => 5,
            'minimum_fee' => 20,
            'approved_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('transport.driver.workspace'))
            ->assertRedirect(route('transport.driver.duty'));

        $this->actingAs($user)
            ->get(route('transport.driver.duty'))
            ->assertOk()
            ->assertSee('Clock in as available')
            ->assertDontSee('Driver Live');

        $this->actingAs($user)
            ->post(route('transport.driver.clock-in'), [
                'transport_vehicle_id' => $vehicle->id,
            ])
            ->assertRedirect(route('transport.driver.workspace'));

        $this->assertDatabaseHas('transport_duty_sessions', [
            'transport_driver_id' => $driver->id,
            'transport_vehicle_id' => $vehicle->id,
            'status' => TransportDutySession::STATUS_AVAILABLE,
            'ended_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('transport.driver.workspace'))
            ->assertOk()
            ->assertSee('Incoming requests')
            ->assertSee('Panic button coming next');
    }

    public function test_client_request_is_offered_to_available_matching_driver_and_can_be_accepted(): void
    {
        $client = User::factory()->create(['role' => 'member']);
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
            'base_fee' => 10,
            'per_km_fee' => 6,
            'minimum_fee' => 25,
            'cancellation_fee' => 12,
            'accepts_cash' => true,
            'accepts_payfast' => true,
            'approved_at' => now(),
        ]);
        $session = TransportDutySession::create([
            'transport_driver_id' => $driver->id,
            'transport_vehicle_id' => $vehicle->id,
            'status' => TransportDutySession::STATUS_AVAILABLE,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($client)
            ->post(route('transport.requests.store'), [
                'service_type' => 'parcel',
                'payment_method' => 'cash',
                'request_timing' => 'immediate',
                'pickup_address' => '10 Church Street',
                'pickup_latitude' => '-33.9249',
                'pickup_longitude' => '18.4241',
                'dropoff_address' => '22 Market Road',
                'dropoff_latitude' => '-33.9180',
                'dropoff_longitude' => '18.4233',
                'distance_km' => '4',
                'passenger_count' => '4',
                'parcel_weight_kg' => '2',
                'required_vehicle_type' => 'bicycle',
                'client_notes' => 'Small parcel.',
            ])
            ->assertRedirect();

        $transportRequest = TransportRequest::with('offers')->firstOrFail();
        $offer = TransportRequestOffer::firstOrFail();

        $this->assertSame(34.0, (float) $transportRequest->quoted_amount);
        $this->assertSame(-33.9249, (float) $transportRequest->pickup_latitude);
        $this->assertSame(18.4241, (float) $transportRequest->pickup_longitude);
        $this->assertSame(-33.9180, (float) $transportRequest->dropoff_latitude);
        $this->assertSame(18.4233, (float) $transportRequest->dropoff_longitude);
        $this->assertSame(0, $transportRequest->passenger_count);
        $this->assertSame(2.0, (float) $transportRequest->parcel_weight_kg);
        $this->assertSame(3.4, (float) $offer->platform_fee);
        $this->assertSame($session->id, $offer->transport_duty_session_id);

        $this->actingAs($driverUser)
            ->get(route('transport.driver.workspace'))
            ->assertOk()
            ->assertSee('10 Church Street')
            ->assertSee('Accept request');

        $this->actingAs($driverUser)
            ->post(route('transport.driver.offers.accept', $offer))
            ->assertRedirect(route('transport.driver.workspace'));

        $this->assertDatabaseHas('transport_requests', [
            'id' => $transportRequest->id,
            'status' => TransportRequest::STATUS_ACCEPTED,
            'accepted_transport_driver_id' => $driver->id,
            'accepted_transport_vehicle_id' => $vehicle->id,
        ]);

        $this->assertDatabaseHas('transport_request_offers', [
            'id' => $offer->id,
            'status' => TransportRequestOffer::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('transport_duty_sessions', [
            'id' => $session->id,
            'status' => TransportDutySession::STATUS_BUSY,
        ]);

        $this->actingAs($client)
            ->post(route('transport.requests.passenger-location', $transportRequest), [
                'latitude' => -33.9248,
                'longitude' => 18.4240,
            ])
            ->assertOk();

        $this->actingAs($driverUser)
            ->post(route('transport.requests.driver-location', $transportRequest), [
                'latitude' => -33.9250,
                'longitude' => 18.4242,
            ])
            ->assertOk();

        $this->actingAs($client)
            ->get(route('transport.requests.tracking', $transportRequest))
            ->assertOk()
            ->assertJsonPath('driver.distance_to_pickup_km', 0)
            ->assertJsonPath('passenger.location.lat', -33.9248);

        $this->actingAs($client)
            ->get(route('transport.requests.show', $transportRequest))
            ->assertOk()
            ->assertSee('Cancel request')
            ->assertSee('Live route')
            ->assertSee('Cancelling now may apply')
            ->assertSee(route('transport.requests.cancel', $transportRequest), false);

        $this->actingAs($client)
            ->post(route('transport.requests.cancel', $transportRequest))
            ->assertRedirect(route('transport.requests.show', $transportRequest));

        $this->assertDatabaseHas('transport_requests', [
            'id' => $transportRequest->id,
            'status' => TransportRequest::STATUS_CANCELLED,
            'cancellation_fee' => 12,
        ]);

        $this->assertDatabaseHas('transport_duty_sessions', [
            'id' => $session->id,
            'status' => TransportDutySession::STATUS_AVAILABLE,
        ]);
    }

    public function test_client_can_save_scheduled_request_when_no_drivers_are_online(): void
    {
        $client = User::factory()->create(['role' => 'member']);
        $scheduledAt = now()->addDay()->setSecond(0);

        $this->actingAs($client)
            ->get(route('transport.requests.create'))
            ->assertOk()
            ->assertSee('No drivers are online right now')
            ->assertSee('My Location')
            ->assertSee('data-address-autocomplete', false)
            ->assertSee("url.searchParams.set('countrycodes', 'za')", false)
            ->assertSee('sortByDistance', false)
            ->assertSee('id="distance_km"', false)
            ->assertSee('updateTripDistance', false)
            ->assertSee('transport-review-modal', false)
            ->assertSee('router.project-osrm.org', false)
            ->assertSee('matchingVehicles', false)
            ->assertSee('Accept and send to drivers')
            ->assertSee('data-service-field="ride"', false)
            ->assertSee('data-service-field="parcel heavy_goods"', false)
            ->assertSee('name="pickup_latitude"', false)
            ->assertSee('name="pickup_longitude"', false)
            ->assertSee('name="dropoff_latitude"', false)
            ->assertSee('name="dropoff_longitude"', false);

        $this->actingAs($client)
            ->post(route('transport.requests.store'), [
                'service_type' => 'ride',
                'payment_method' => 'payfast',
                'request_timing' => 'scheduled',
                'scheduled_pickup_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'pickup_address' => '1 Main Road',
                'dropoff_address' => '2 Station Street',
                'distance_km' => '8',
                'passenger_count' => '2',
                'parcel_weight_kg' => '15',
                'required_vehicle_type' => 'car',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transport_requests', [
            'user_id' => $client->id,
            'service_type' => 'ride',
            'status' => TransportRequest::STATUS_SCHEDULED,
            'request_timing' => 'scheduled',
            'passenger_count' => 2,
            'parcel_weight_kg' => null,
            'accepted_transport_driver_id' => null,
        ]);

        $this->assertDatabaseCount('transport_request_offers', 0);

        $request = TransportRequest::firstOrFail();

        $this->actingAs($client)
            ->get(route('transport.requests.show', $request))
            ->assertOk()
            ->assertSee('This request is scheduled')
            ->assertSee('Cancel request');

        $this->actingAs($client)
            ->post(route('transport.requests.cancel', $request))
            ->assertRedirect(route('transport.requests.show', $request));

        $this->assertDatabaseHas('transport_requests', [
            'id' => $request->id,
            'status' => TransportRequest::STATUS_CANCELLED,
            'cancellation_fee' => 0,
        ]);
    }
}
