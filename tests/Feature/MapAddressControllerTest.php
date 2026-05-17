<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapAddressControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_places_autocomplete_maps_south_african_results_without_exposing_key(): void
    {
        config([
            'services.google_maps.key' => 'google-maps-test-key',
            'services.google_maps.places_endpoint' => 'https://places.googleapis.com/v1',
        ]);

        Http::fake(function ($request) {
            $payload = $request->data();

            $this->assertSame('https://places.googleapis.com/v1/places:autocomplete', (string) $request->url());
            $this->assertTrue($request->hasHeader('X-Goog-Api-Key', 'google-maps-test-key'));
            $this->assertSame('10 Church Street', $payload['input']);
            $this->assertSame(['za'], $payload['includedRegionCodes']);
            $this->assertSame(-28.233, $payload['locationBias']['circle']['center']['latitude']);
            $this->assertSame(28.307, $payload['locationBias']['circle']['center']['longitude']);

            return Http::response([
                'suggestions' => [
                    [
                        'placePrediction' => [
                            'placeId' => 'places/test-place',
                            'text' => ['text' => '10 Church Street, Bethlehem, South Africa'],
                            'structuredFormat' => [
                                'mainText' => ['text' => '10 Church Street'],
                                'secondaryText' => ['text' => 'Bethlehem, South Africa'],
                            ],
                        ],
                    ],
                ],
            ]);
        });

        $this->getJson(route('maps.places.autocomplete', [
            'q' => '10 Church Street',
            'lat' => -28.233,
            'lng' => 28.307,
        ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('provider', 'google')
            ->assertJsonPath('results.0.place_id', 'places/test-place')
            ->assertJsonPath('results.0.label', '10 Church Street')
            ->assertJsonMissing(['google-maps-test-key']);
    }

    public function test_google_place_details_maps_coordinates(): void
    {
        config([
            'services.google_maps.key' => 'google-maps-test-key',
            'services.google_maps.places_endpoint' => 'https://places.googleapis.com/v1',
        ]);

        Http::fake([
            'https://places.googleapis.com/v1/places/places%2Ftest-place' => Http::response([
                'id' => 'places/test-place',
                'formattedAddress' => '10 Church Street, Bethlehem, 9701, South Africa',
                'displayName' => ['text' => '10 Church Street'],
                'location' => [
                    'latitude' => -28.2329,
                    'longitude' => 28.3071,
                ],
            ]),
        ]);

        $this->getJson(route('maps.places.details', ['place_id' => 'places/test-place']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.label', '10 Church Street')
            ->assertJsonPath('result.lat', -28.2329)
            ->assertJsonPath('result.lon', 28.3071);
    }

    public function test_google_reverse_geocoding_returns_formatted_address(): void
    {
        config([
            'services.google_maps.key' => 'google-maps-test-key',
            'services.google_maps.geocoding_endpoint' => 'https://maps.googleapis.com/maps/api/geocode/json',
        ]);

        Http::fake(function ($request) {
            $this->assertStringStartsWith('https://maps.googleapis.com/maps/api/geocode/json?', (string) $request->url());
            $this->assertStringContainsString('key=google-maps-test-key', (string) $request->url());
            $this->assertStringContainsString('latlng=-28.2329%2C28.3071', (string) $request->url());

            return Http::response([
                'status' => 'OK',
                'results' => [
                    ['formatted_address' => '10 Church Street, Bethlehem, 9701, South Africa'],
                ],
            ]);
        });

        $this->getJson(route('maps.places.reverse', ['lat' => -28.2329, 'lng' => 28.3071]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('address', '10 Church Street, Bethlehem, 9701, South Africa');
    }

    public function test_unconfigured_maps_endpoint_returns_manual_entry_fallback_payload(): void
    {
        config(['services.google_maps.key' => '']);
        Http::fake();

        $this->getJson(route('maps.places.autocomplete', ['q' => 'Church Street']))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('configured', false)
            ->assertJsonPath('results', []);

        Http::assertNothingSent();
    }

    public function test_dev_owner_can_save_google_maps_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'jameskoen78@gmail.com',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dev.maps.key.store'), [
                'google_maps_api_key' => 'google-maps-test-key',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('configured', true)
            ->assertJsonPath('source', 'Settings')
            ->assertJsonMissing(['google-maps-test-key']);

        $this->assertSame('google-maps-test-key', Setting::getValue('maps.google_api_key'));
    }
}
