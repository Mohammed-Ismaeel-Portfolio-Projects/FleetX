<?php

namespace Tests\Unit;

use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Application\Pipelines\RoutingPipeline;
use App\Simulation\Routing\Application\Services\RoutePlanner;
use App\Simulation\Routing\Infrastructure\Providers\GraphHopper\GraphHopperProvider;
use App\Simulation\Routing\ValueObjects\Coordinate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end routing pipeline: GraphHopper primary with Simulator fallback.
 */
class GraphHopperRoutingPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GraphHopperProvider::resetProfileCompatibilityCache();
    }

    private function makeRequest(): RoutePlanningRequest
    {
        $origin = new Coordinate(52.5200, 13.4050); // Berlin
        $destination = new Coordinate(48.8566, 2.3522); // Paris
        $vehicle = VehicleProfile::truck(4.0, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        $options = RoutePlanningOptions::createDefault();
        $context = RoutePlanningContext::withShipment('pipeline-shipment', $vehicle, $options);

        return RoutePlanningRequest::create('pipeline-planning', $origin, $destination, $context);
    }

    public function test_pipeline_uses_graphhopper_when_available(): void
    {
        Config::set('routing.default_provider', 'graphhopper');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.fallback_provider', 'simulator');
        Config::set('routing.graphhopper.api_key', 'test-key');
        Config::set('routing.graphhopper.url', 'https://graphhopper.com/api/1');

        Http::fake([
            'graphhopper.com/*' => Http::response([
                'paths' => [
                    [
                        'distance' => 1050000,
                        'time' => 36000000,
                        'points' => [
                            'type' => 'LineString',
                            'coordinates' => [
                                [13.4050, 52.5200],
                                [8.6821, 50.1109],
                                [2.3522, 48.8566],
                            ],
                        ],
                        'instructions' => [
                            ['text' => 'Drive', 'distance' => 1050000, 'time' => 36000000, 'sign' => 0],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var RoutePlanner $planner */
        $planner = $this->app->make(RoutePlanner::class);
        $result = $planner->planRoute($this->makeRequest());

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('graphhopper', $result->providerName);
        $this->assertNotNull($result->route);
        $this->assertGreaterThan(0, $result->route->distance->toMeters());
        $this->assertGreaterThanOrEqual(2, $result->route->geometry->getPolyline()->getCoordinateCount());
    }

    public function test_pipeline_falls_back_to_simulator_without_api_key(): void
    {
        Config::set('routing.default_provider', 'graphhopper');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.fallback_provider', 'simulator');
        Config::set('routing.graphhopper.api_key', '');

        /** @var RoutePlanner $planner */
        $planner = $this->app->make(RoutePlanner::class);
        $result = $planner->planRoute($this->makeRequest());

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('simulator', $result->providerName);
        $this->assertNotNull($result->route);
    }

    public function test_pipeline_falls_back_when_graphhopper_http_fails(): void
    {
        Config::set('routing.default_provider', 'graphhopper');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.fallback_provider', 'simulator');
        Config::set('routing.graphhopper.api_key', 'test-key');
        Config::set('routing.graphhopper.url', 'https://graphhopper.com/api/1');

        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'Quota exceeded'], 429),
        ]);

        /** @var RoutePlanner $planner */
        $planner = $this->app->make(RoutePlanner::class);
        $result = $planner->planRoute($this->makeRequest());

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('simulator', $result->providerName);
    }

    public function test_pipeline_passes_heavy_truck_profile_to_graphhopper(): void
    {
        Config::set('routing.default_provider', 'graphhopper');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.fallback_provider', 'simulator');
        Config::set('routing.graphhopper.api_key', 'test-key');
        Config::set('routing.graphhopper.url', 'https://graphhopper.com/api/1');
        Config::set('routing.graphhopper.enable_truck_restrictions', true);
        Config::set('routing.graphhopper.enable_flexible_routing', true);

        Http::fake([
            'graphhopper.com/*' => Http::response([
                'paths' => [
                    [
                        'distance' => 900000,
                        'time' => 32000000,
                        'points' => [
                            'type' => 'LineString',
                            'coordinates' => [
                                [13.4050, 52.5200],
                                [2.3522, 48.8566],
                            ],
                        ],
                        'instructions' => [],
                    ],
                ],
            ], 200),
        ]);

        $request = $this->makeRequest();
        $this->assertSame(VehicleProfile::FREIGHT_HEAVY_TRUCK, $request->context->vehicle->freightClass);
        $this->assertSame(40000.0, $request->context->vehicle->weight);

        /** @var RoutePlanner $planner */
        $planner = $this->app->make(RoutePlanner::class);
        $result = $planner->planRoute($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('graphhopper', $result->providerName);

        Http::assertSent(function ($httpRequest) {
            $data = $httpRequest->data();

            return $httpRequest->method() === 'POST'
                && ($data['profile'] ?? null) === 'truck'
                && isset($data['custom_model']['priority'][0]['if'])
                && str_contains($data['custom_model']['priority'][0]['if'], 'max_weight < 40.000');
        });
    }
}
