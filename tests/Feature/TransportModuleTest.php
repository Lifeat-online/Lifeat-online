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
                'distance_km' => '4',
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
            ->assertSee('name="pickup_latitude"', false)
            ->assertSee('name="pickup_longitude"', false);

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
                'required_vehicle_type' => 'car',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transport_requests', [
            'user_id' => $client->id,
            'service_type' => 'ride',
            'status' => TransportRequest::STATUS_SCHEDULED,
            'request_timing' => 'scheduled',
            'accepted_transport_driver_id' => null,
        ]);

        $this->assertDatabaseCount('transport_request_offers', 0);

        $request = TransportRequest::firstOrFail();

        $this->actingAs($client)
            ->get(route('transport.requests.show', $request))
            ->assertOk()
            ->assertSee('This request is scheduled');
    }
}
