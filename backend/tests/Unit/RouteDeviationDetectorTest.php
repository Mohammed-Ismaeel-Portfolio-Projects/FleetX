<?php

namespace Tests\Unit;

use App\Actions\Shipment\RerouteShipmentAction;
use App\Domain\Events\RouteDeviationDetected;
use App\Events\Tracking\TrackingStateUpdated;
use App\Listeners\Tracking\DetectRouteDeviationListener;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\TrackingState;
use App\Models\Vehicle;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Simulation\Routing\Application\Services\RouteDeviationDetector;
use App\Simulation\Routing\Enums\RouteDeviationStatus;
use App\Simulation\Routing\ValueObjects\Coordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteDeviationDetectorTest extends TestCase
{
    use RefreshDatabase;

    private RouteDeviationDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new RouteDeviationDetector();
        Config::set('routing.deviation.threshold_meters', 100);
        Config::set('routing.deviation.enabled', true);
    }

    /**
     * Simple north-south polyline near Berlin.
     *
     * @return Coordinate[]
     */
    private function sampleRoute(): array
    {
        return [
            new Coordinate(52.5200, 13.4050),
            new Coordinate(52.5300, 13.4050),
            new Coordinate(52.5400, 13.4050),
        ];
    }

    public function test_vehicle_exactly_on_route_is_on_route(): void
    {
        $result = $this->detector->detect(
            latitude: 52.5300,
            longitude: 13.4050,
            routeCoordinates: $this->sampleRoute(),
            shipmentId: 'ship-1',
        );

        $this->assertFalse($result->isDeviated);
        $this->assertSame(RouteDeviationStatus::ON_ROUTE, $result->routeStatus);
        $this->assertLessThanOrEqual(5.0, $result->deviationDistanceMeters);
    }

    public function test_vehicle_50_meters_away_is_on_route(): void
    {
        // ~50m east of the route (~0.00074 deg lon at this latitude)
        $result = $this->detector->detect(
            latitude: 52.5300,
            longitude: 13.4050 + 0.00074,
            routeCoordinates: $this->sampleRoute(),
            shipmentId: 'ship-1',
        );

        $this->assertFalse($result->isDeviated);
        $this->assertSame(RouteDeviationStatus::ON_ROUTE, $result->routeStatus);
        $this->assertLessThanOrEqual(100.0, $result->deviationDistanceMeters);
        $this->assertGreaterThan(20.0, $result->deviationDistanceMeters);
    }

    public function test_vehicle_500_meters_away_is_deviated(): void
    {
        // ~500m east (~0.0074 deg lon)
        $result = $this->detector->detect(
            latitude: 52.5300,
            longitude: 13.4050 + 0.0074,
            routeCoordinates: $this->sampleRoute(),
            shipmentId: 'ship-1',
        );

        $this->assertTrue($result->isDeviated);
        $this->assertSame(RouteDeviationStatus::DEVIATED, $result->routeStatus);
        $this->assertGreaterThan(100.0, $result->deviationDistanceMeters);
    }

    public function test_vehicle_completely_outside_route_is_deviated(): void
    {
        $result = $this->detector->detect(
            latitude: 48.8566,
            longitude: 2.3522,
            routeCoordinates: $this->sampleRoute(),
            shipmentId: 'ship-1',
        );

        $this->assertTrue($result->isDeviated);
        $this->assertSame(RouteDeviationStatus::DEVIATED, $result->routeStatus);
        $this->assertGreaterThan(1000.0, $result->deviationDistanceMeters);
    }

    public function test_no_planned_route_skips_detection(): void
    {
        Event::fake([RouteDeviationDetected::class]);

        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'shipment_id' => $shipment->id,
            'is_active' => true,
        ]);

        $state = TrackingState::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $assignment->trailer_id,
            'latitude' => 52.53,
            'longitude' => 13.41,
            'heading' => 0,
            'speed' => 40,
            'accuracy' => 5,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
            'last_update_at' => now(),
        ]);

        $listener = $this->app->make(DetectRouteDeviationListener::class);
        $listener->handle(new TrackingStateUpdated($state));

        Event::assertNotDispatched(RouteDeviationDetected::class);
    }

    public function test_listener_dispatches_when_vehicle_deviates_from_cached_route(): void
    {
        Event::fake([RouteDeviationDetected::class]);

        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'shipment_id' => $shipment->id,
            'is_active' => true,
        ]);

        app(PlannedRouteCache::class)->rememberRoute(
            shipmentId: $shipment->id,
            coordinates: array_map(fn (Coordinate $c) => $c->toArray(), $this->sampleRoute()),
            destination: ['latitude' => 52.5400, 'longitude' => 13.4050],
            origin: ['latitude' => 52.5200, 'longitude' => 13.4050],
            apiPayload: ['route' => ['geometry' => ['coordinates' => []]]],
            provider: 'graphhopper',
        );

        $state = TrackingState::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $assignment->trailer_id,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'heading' => 0,
            'speed' => 60,
            'accuracy' => 5,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
            'last_update_at' => now(),
        ]);

        $listener = $this->app->make(DetectRouteDeviationListener::class);
        $listener->handle(new TrackingStateUpdated($state));

        Event::assertDispatched(RouteDeviationDetected::class, function (RouteDeviationDetected $event) use ($shipment, $vehicle) {
            return $event->shipmentId === $shipment->id
                && $event->vehicleId === $vehicle->id
                && $event->deviationDistance > 100;
        });
    }

    public function test_rerouting_uses_truck_profile_dimensions(): void
    {
        Config::set('routing.default_provider', 'graphhopper');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.graphhopper.api_key', 'test-key');
        Config::set('routing.graphhopper.url', 'https://graphhopper.com/api/1');
        Config::set('routing.graphhopper.enable_truck_restrictions', true);
        Config::set('routing.graphhopper.enable_flexible_routing', true);

        Http::fake([
            'graphhopper.com/*' => Http::response([
                'paths' => [[
                    'distance' => 120000,
                    'time' => 5000000,
                    'points' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [13.4050, 52.5200],
                            [2.3522, 48.8566],
                        ],
                    ],
                    'instructions' => [],
                ]],
            ], 200),
        ]);

        $vehicle = Vehicle::factory()->create([
            'routing_class' => Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
            'gross_weight_kg' => 40000,
            'height_m' => 4.0,
            'width_m' => 2.55,
            'length_m' => 16.5,
        ]);

        $shipment = Shipment::factory()->create([
            'pickup_city' => 'Berlin',
            'delivery_city' => 'Paris',
        ]);

        Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => null,
            'is_active' => true,
        ]);

        /** @var RerouteShipmentAction $action */
        $action = $this->app->make(RerouteShipmentAction::class);
        $result = $action->execute($shipment->id, 52.5200, 13.4050);

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->method() === 'POST'
                && ($data['profile'] ?? null) === 'truck'
                && isset($data['custom_model']['priority'][0]['if'])
                && str_contains($data['custom_model']['priority'][0]['if'], 'max_height < 4.00')
                && str_contains($data['custom_model']['priority'][0]['if'], 'max_weight < 40.000');
        });

        $cached = app(PlannedRouteCache::class)->get($shipment->id);
        $this->assertNotNull($cached);
        $this->assertTrue((bool) ($cached['is_reroute'] ?? false));
    }
}
