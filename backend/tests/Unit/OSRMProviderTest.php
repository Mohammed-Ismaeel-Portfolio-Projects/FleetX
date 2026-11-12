<?php

namespace Tests\Unit;

use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Infrastructure\Providers\OSRM\OSRMProvider;
use App\Simulation\Routing\ValueObjects\Coordinate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OSRMProviderTest
 * 
 * Unit tests for OSRMProvider.
 */
class OSRMProviderTest extends TestCase
{
    private OSRMProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new OSRMProvider();
    }

    /**
     * Test request URL generation.
     */
    public function test_builds_correct_request_url()
    {
        // Set configuration
        Config::set('routing.osrm.url', 'http://localhost:5000');
        Config::set('routing.osrm.profile', 'driving');

        // Create planning request
        $origin = new Coordinate(52.5200, 13.4050);
        $destination = new Coordinate(48.8566, 2.3522);
        $vehicleProfile = VehicleProfile::truck(4.0, 2.5, 12.0, 18000);
        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment('test-shipment', $vehicleProfile, $planningOptions);
        $request = RoutePlanningRequest::create('test-planning', $origin, $destination, $planningContext);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('buildRequestUrl');
        $method->setAccessible(true);

        $url = $method->invoke($this->provider, $origin, $destination);

        // Verify URL format
        $this->assertStringContainsString('http://localhost:5000/route/v1/driving', $url);
        $this->assertStringContainsString('13.405000,52.520000', $url);
        $this->assertStringContainsString('2.352200,48.856600', $url);
        $this->assertStringContainsString('steps=true', $url);
        $this->assertStringContainsString('overview=full', $url);
        $this->assertStringContainsString('alternatives=false', $url);
        $this->assertStringContainsString('geometries=polyline', $url);
        $this->assertStringContainsString('annotations=false', $url);
    }

    /**
     * Test successful response.
     */
    public function test_successful_response_returns_osrm_json()
    {
        // Mock successful OSRM response
        Http::fake([
            'localhost:5000/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'geometry' => 'encoded_polyline',
                        'legs' => [
                            [
                                'steps' => [],
                                'distance' => 923456.7,
                                'duration' => 41400.5,
                            ]
                        ],
                        'distance' => 923456.7,
                        'duration' => 41400.5,
                    ]
                ],
                'waypoints' => [],
            ], 200),
        ]);

        Config::set('routing.osrm.url', 'http://localhost:5000');
        Config::set('routing.osrm.timeout', 30);
        Config::set('routing.osrm.retry_attempts', 1);
        Config::set('routing.osrm.retry_delay', 100);

        // Create planning request
        $origin = new Coordinate(52.5200, 13.4050);
        $destination = new Coordinate(48.8566, 2.3522);
        $vehicleProfile = VehicleProfile::truck(4.0, 2.5, 12.0, 18000);
        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment('test-shipment', $vehicleProfile, $planningOptions);
        $request = RoutePlanningRequest::create('test-planning', $origin, $destination, $planningContext);

        // Call calculateRoute
        $response = $this->provider->calculateRoute($request);

        // Verify response
        $this->assertIsArray($response);
        $this->assertEquals('Ok', $response['code']);
        $this->assertArrayHasKey('routes', $response);
        $this->assertArrayHasKey('waypoints', $response);
        $this->assertTrue($this->provider->isAvailable());
    }

    /**
     * Test timeout handling.
     */
    public function test_timeout_throws_exception()
    {
        // Mock timeout
        Http::fake([
            'localhost:5000/*' => Http::timeout(1)->response([], 200),
        ]);

        Config::set('routing.osrm.url', 'http://localhost:5000');
        Config::set('routing.osrm.timeout', 0.001); // Very short timeout
        Config::set('routing.osrm.retry_attempts', 1);
        Config::set('routing.osrm.retry_delay', 100);

        // Create planning request
        $origin = new Coordinate(52.5200, 13.4050);
        $destination = new Coordinate(48.8566, 2.3522);
        $vehicleProfile = VehicleProfile::truck(4.0, 2.5, 12.0, 18000);
        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment('test-shipment', $vehicleProfile, $planningOptions);
        $request = RoutePlanningRequest::create('test-planning', $origin, $destination, $planningContext);

        // Expect exception
        $this->expectException(\App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException::class);

        $this->provider->calculateRoute($request);

        // Verify provider status
        $this->assertFalse($this->provider->isAvailable());
        $this->assertNotNull($this->provider->getLastError());
    }

    /**
     * Test invalid response (HTTP error).
     */
    public function test_http_error_throws_exception()
    {
        // Mock HTTP error
        Http::fake([
            'localhost:5000/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        Config::set('routing.osrm.url', 'http://localhost:5000');
        Config::set('routing.osrm.timeout', 30);
        Config::set('routing.osrm.retry_attempts', 1);
        Config::set('routing.osrm.retry_delay', 100);

        // Create planning request
        $origin = new Coordinate(52.5200, 13.4050);
        $destination = new Coordinate(48.8566, 2.3522);
        $vehicleProfile = VehicleProfile::truck(4.0, 2.5, 12.0, 18000);
        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment('test-shipment', $vehicleProfile, $planningOptions);
        $request = RoutePlanningRequest::create('test-planning', $origin, $destination, $planningContext);

        // Expect exception
        $this->expectException(\App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException::class);

        $this->provider->calculateRoute($request);

        // Verify provider status
        $this->assertFalse($this->provider->isAvailable());
    }

    /**
     * Test invalid OSRM response (error code).
     */
    public function test_osrm_error_code_throws_exception()
    {
        // Mock OSRM error response
        Http::fake([
            'localhost:5000/*' => Http::response([
                'code' => 'NoRoute',
                'message' => 'No route found',
            ], 200),
        ]);

        Config::set('routing.osrm.url', 'http://localhost:5000');
        Config::set('routing.osrm.timeout', 30);
        Config::set('routing.osrm.retry_attempts', 1);
        Config::set('routing.osrm.retry_delay', 100);

        // Create planning request
        $origin = new Coordinate(52.5200, 13.4050);
        $destination = new Coordinate(48.8566, 2.3522);
        $vehicleProfile = VehicleProfile::truck(4.0, 2.5, 12.0, 18000);
        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment('test-shipment', $vehicleProfile, $planningOptions);
        $request = RoutePlanningRequest::create('test-planning', $origin, $destination, $planningContext);

        // Expect exception
        $this->expectException(\App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException::class);

        $this->provider->calculateRoute($request);

        // Verify provider status
        $this->assertFalse($this->provider->isAvailable());
    }

    /**
     * Test invalid JSON response.
     */
    public function test_invalid_json_throws_exception()
    {
        // Mock invalid JSON
        Http::fake([
            'localhost:5000/*' => Http::response('invalid json', 200),
        ]);

        Config::set('routing.osrm.url', 'http://localhost:5000');
        Config::set('routing.osrm.timeout', 30);
        Config::set('routing.osrm.retry_attempts', 1);
        Config::set('routing.osrm.retry_delay', 100);

        // Create planning request
        $origin = new Coordinate(52.5200, 13.4050);
        $destination = new Coordinate(48.8566, 2.3522);
        $vehicleProfile = VehicleProfile::truck(4.0, 2.5, 12.0, 18000);
        $planningOptions = RoutePlanningOptions::createDefault();
        $planningContext = RoutePlanningContext::withShipment('test-shipment', $vehicleProfile, $planningOptions);
        $request = RoutePlanningRequest::create('test-planning', $origin, $destination, $planningContext);

        // Expect exception
        $this->expectException(\App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException::class);

        $this->provider->calculateRoute($request);

        // Verify provider status
        $this->assertFalse($this->provider->isAvailable());
    }

    /**
     * Test provider name.
     */
    public function test_get_provider_name()
    {
        $this->assertEquals('osrm', $this->provider->getProviderName());
    }

    /**
     * Test provider version.
     */
    public function test_get_provider_version()
    {
        $this->assertEquals('v5.0', $this->provider->getProviderVersion());
    }

    /**
     * Test provider status.
     */
    public function test_provider_status_management()
    {
        // Initially available
        $this->assertTrue($this->provider->isAvailable());

        // Set unavailable
        $this->provider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::UNAVAILABLE);
        $this->assertFalse($this->provider->isAvailable());

        // Set available again
        $this->provider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::AVAILABLE);
        $this->assertTrue($this->provider->isAvailable());
    }
}
