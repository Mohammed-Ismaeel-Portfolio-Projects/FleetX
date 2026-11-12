<?php

namespace Tests\Unit;

use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use App\Simulation\Routing\Infrastructure\Providers\GraphHopper\GraphHopperProvider;
use App\Simulation\Routing\ValueObjects\Coordinate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GraphHopperProviderTest extends TestCase
{
    private GraphHopperProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new GraphHopperProvider();
        GraphHopperProvider::resetProfileCompatibilityCache();

        Config::set('routing.graphhopper.url', 'https://graphhopper.com/api/1');
        Config::set('routing.graphhopper.api_key', 'test-api-key');
        Config::set('routing.graphhopper.profile', 'truck');
        Config::set('routing.graphhopper.vehicle_type', 'heavy_truck');
        Config::set('routing.graphhopper.enable_truck_restrictions', true);
        Config::set('routing.graphhopper.enable_flexible_routing', true);
        Config::set('routing.graphhopper.timeout', 30);
    }

    private function makeRequest(?VehicleProfile $vehicle = null): RoutePlanningRequest
    {
        $origin = new Coordinate(52.5200, 13.4050);
        $destination = new Coordinate(48.8566, 2.3522);
        $vehicle ??= VehicleProfile::truck(4.0, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        $options = RoutePlanningOptions::createDefault();
        $context = RoutePlanningContext::withShipment('test-shipment', $vehicle, $options);

        return RoutePlanningRequest::create('test-planning', $origin, $destination, $context);
    }

    public function test_builds_truck_aware_request_payload(): void
    {
        $vehicle = VehicleProfile::truck(4.0, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        $payload = $this->provider->buildRequestPayload(
            new Coordinate(52.5200, 13.4050),
            new Coordinate(48.8566, 2.3522),
            $vehicle
        );

        $this->assertSame('truck', $payload['profile']);
        $this->assertTrue($payload['points_encoded']);
        $this->assertTrue($payload['ch.disable']);
        $this->assertSame([
            [13.4050, 52.5200],
            [2.3522, 48.8566],
        ], $payload['points']);
        $this->assertArrayHasKey('custom_model', $payload);
        $this->assertStringContainsString('max_height < 4.00', $payload['custom_model']['priority'][0]['if']);
        $this->assertStringContainsString('max_width < 2.55', $payload['custom_model']['priority'][0]['if']);
        $this->assertStringContainsString('max_length < 16.50', $payload['custom_model']['priority'][0]['if']);
        $this->assertStringContainsString('max_weight < 40.000', $payload['custom_model']['priority'][0]['if']);
    }

    public function test_light_truck_maps_to_small_truck_profile(): void
    {
        $vehicle = VehicleProfile::truck(3.0, 2.35, 8.2, 7500, 90, false, VehicleProfile::FREIGHT_LIGHT_TRUCK);

        $this->assertSame('small_truck', $this->provider->resolveGraphHopperProfile($vehicle));
    }

    public function test_van_maps_to_small_truck_profile(): void
    {
        $vehicle = VehicleProfile::truck(2.6, 2.1, 5.8, 3200, 90, false, VehicleProfile::FREIGHT_VAN);

        $this->assertSame('small_truck', $this->provider->resolveGraphHopperProfile($vehicle));
    }

    public function test_truck_dimensions_omitted_when_restrictions_disabled(): void
    {
        Config::set('routing.graphhopper.enable_truck_restrictions', false);

        $vehicle = VehicleProfile::truck(4.0, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        $payload = $this->provider->buildRequestPayload(
            new Coordinate(52.5200, 13.4050),
            new Coordinate(48.8566, 2.3522),
            $vehicle
        );

        $this->assertSame('truck', $payload['profile']);
        $this->assertArrayNotHasKey('custom_model', $payload);
        $this->assertArrayNotHasKey('ch.disable', $payload);
    }

    public function test_classic_mode_omits_flexible_fields_and_uses_configured_profile(): void
    {
        Config::set('routing.graphhopper.enable_flexible_routing', false);
        Config::set('routing.graphhopper.profile', 'car');
        Config::set('routing.graphhopper.enable_truck_restrictions', true);

        $vehicle = VehicleProfile::truck(4.0, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        $payload = $this->provider->buildRequestPayload(
            new Coordinate(48.1351, 11.5820),
            new Coordinate(48.2082, 16.3738),
            $vehicle
        );

        $this->assertSame('car', $payload['profile']);
        $this->assertTrue($payload['points_encoded']);
        $this->assertTrue($payload['calc_points']);
        $this->assertTrue($payload['instructions']);
        $this->assertSame([
            [11.5820, 48.1351],
            [16.3738, 48.2082],
        ], $payload['points']);
        $this->assertArrayNotHasKey('custom_model', $payload);
        $this->assertArrayNotHasKey('ch.disable', $payload);
        $this->assertSame(
            ['points', 'profile', 'points_encoded', 'instructions', 'calc_points'],
            array_keys($payload)
        );
    }

    public function test_successful_api_response(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response([
                'paths' => [
                    [
                        'distance' => 1050000.5,
                        'time' => 42000000,
                        'points' => '_p~iF~ps|U_ulL~qC@',
                        'instructions' => [
                            ['text' => 'Head north', 'distance' => 100, 'time' => 10000, 'sign' => 0],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->provider->calculateRoute($this->makeRequest());

        $this->assertArrayHasKey('paths', $response);
        $this->assertNotEmpty($response['paths']);
        $this->assertEquals(1050000.5, $response['paths'][0]['distance']);
        $this->assertTrue($this->provider->isAvailable());

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->method() === 'POST'
                && str_contains($request->url(), '/route')
                && ($data['profile'] ?? null) === 'truck'
                && isset($data['custom_model']);
        });
    }

    public function test_http_error_throws_unavailable(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(RoutingProviderUnavailableException::class);
        try {
            $this->provider->calculateRoute($this->makeRequest());
        } catch (RoutingProviderUnavailableException $e) {
            $this->assertFalse($this->provider->isAvailable());
            throw $e;
        }
    }

    public function test_client_error_does_not_permanently_disable_provider(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'Too many points'], 400),
        ]);

        try {
            $this->provider->calculateRoute($this->makeRequest());
            $this->fail('Expected RoutingProviderUnavailableException');
        } catch (RoutingProviderUnavailableException) {
            // expected
        }

        $this->assertTrue($this->provider->isAvailable());
    }

    public function test_api_error_message_throws_unavailable(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response([
                'message' => 'Point not found',
            ], 200),
        ]);

        $this->expectException(RoutingProviderUnavailableException::class);
        $this->expectExceptionMessage('GraphHopper API error: Point not found');
        $this->provider->calculateRoute($this->makeRequest());
    }

    public function test_invalid_response_missing_paths(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['info' => []], 200),
        ]);

        $this->expectException(RoutingProviderUnavailableException::class);
        $this->expectExceptionMessage('no route paths');
        $this->provider->calculateRoute($this->makeRequest());
    }

    public function test_unavailable_without_api_key(): void
    {
        Config::set('routing.graphhopper.api_key', '');

        $this->assertFalse($this->provider->isAvailable());

        $this->expectException(RoutingProviderUnavailableException::class);
        $this->provider->calculateRoute($this->makeRequest());
    }

    public function test_timeout_is_treated_as_unavailable(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(RoutingProviderUnavailableException::class);
        $this->provider->calculateRoute($this->makeRequest());
    }

    public function test_retries_with_car_when_truck_profile_unsupported(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::sequence()
                ->push([
                    'message' => 'For your account the profile parameter can only be one of [car, bike, foot] but was truck',
                ], 400)
                ->push([
                    'paths' => [
                        [
                            'distance' => 1050000.5,
                            'time' => 42000000,
                            'points' => '_p~iF~ps|U_ulL~qC@',
                            'instructions' => [],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->provider->calculateRoute($this->makeRequest());

        $this->assertArrayHasKey('paths', $response);
        $this->assertEquals(1050000.5, $response['paths'][0]['distance']);
        $this->assertTrue($this->provider->isAvailable());

        Http::assertSentCount(2);

        $profiles = [];
        Http::assertSent(function ($request) use (&$profiles) {
            if ($request->method() === 'POST') {
                $profiles[] = $request->data()['profile'] ?? null;
            }

            return true;
        });

        $this->assertSame(['truck', 'car'], $profiles);
    }

    public function test_does_not_retry_car_for_unrelated_400_errors(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'Too many points for Routing API'], 400),
        ]);

        try {
            $this->provider->calculateRoute($this->makeRequest());
            $this->fail('Expected RoutingProviderUnavailableException');
        } catch (RoutingProviderUnavailableException) {
            // expected — unrelated 400 must not be rewritten as car
        }

        Http::assertSentCount(1);
        $this->assertTrue($this->provider->isAvailable());
    }

    public function test_subsequent_requests_use_car_after_unsupported_truck_detection(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::sequence()
                ->push([
                    'message' => 'For your account the profile parameter can only be one of [car, bike, foot] but was truck',
                ], 400)
                ->push([
                    'paths' => [['distance' => 1000, 'time' => 1000, 'points' => 'abc', 'instructions' => []]],
                ], 200)
                ->push([
                    'paths' => [['distance' => 2000, 'time' => 2000, 'points' => 'def', 'instructions' => []]],
                ], 200),
        ]);

        $this->provider->calculateRoute($this->makeRequest());
        $this->provider->calculateRoute($this->makeRequest());

        $profiles = [];
        Http::assertSent(function ($request) use (&$profiles) {
            if ($request->method() === 'POST') {
                $profiles[] = $request->data()['profile'] ?? null;
            }

            return true;
        });

        // 1st call: truck then car; 2nd call: car only (sticky compatibility)
        $this->assertSame(['truck', 'car', 'car'], $profiles);
    }
}
