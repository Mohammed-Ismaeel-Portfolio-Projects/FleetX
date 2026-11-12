<?php

namespace Tests\Unit\Simulation;

use App\Models\Assignment;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Simulation\Progress\RouteProgressEngine;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RouteProgressEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_advances_along_cached_polyline_and_increases_completion(): void
    {
        Log::spy();

        $shipmentId = 'ship-progress-1';
        $vehicleId = 'veh-progress-1';

        $coordinates = [
            ['latitude' => 48.1351, 'longitude' => 11.5820], // Munich
            ['latitude' => 48.1500, 'longitude' => 12.5000],
            ['latitude' => 48.1800, 'longitude' => 14.0000],
            ['latitude' => 48.2082, 'longitude' => 16.3738], // Vienna
        ];

        $cache = Mockery::mock(PlannedRouteCache::class);
        $cache->shouldReceive('get')->with($shipmentId)->andReturn([
            'coordinates' => $coordinates,
            'provider' => 'graphhopper',
            'api_payload' => [
                'route' => ['id' => 'route-gh-1'],
            ],
        ]);
        $cache->shouldReceive('hasUsableRoute')->andReturn(true);

        $engine = new RouteProgressEngine($cache);

        $assignment = $this->fakeAssignment($shipmentId, $vehicleId);

        $first = $engine->advance($assignment, 90.0, 60);
        $this->assertNotNull($first);
        $this->assertGreaterThan(0, $first->distanceTravelledM);
        $this->assertLessThan($first->totalDistanceM, $first->distanceTravelledM);
        $this->assertGreaterThan(0, $first->completionPercentage);
        $this->assertFalse($first->completed);

        $second = $engine->advance($assignment, 90.0, 60);
        $this->assertNotNull($second);
        $this->assertGreaterThan($first->distanceTravelledM, $second->distanceTravelledM);
        $this->assertLessThan($first->remainingDistanceM, $second->remainingDistanceM);
        $this->assertGreaterThan($first->completionPercentage, $second->completionPercentage);

        $this->assertGreaterThanOrEqual(48.1351, $second->currentLatitude);
        $this->assertLessThanOrEqual(48.2082, $second->currentLatitude);
        $this->assertGreaterThanOrEqual(11.5820, $second->currentLongitude);
        $this->assertLessThanOrEqual(16.3738, $second->currentLongitude);
    }

    public function test_returns_null_when_planned_route_missing(): void
    {
        Log::spy();

        $shipmentId = 'ship-progress-2';
        $vehicleId = 'veh-progress-2';

        $cache = Mockery::mock(PlannedRouteCache::class);
        $cache->shouldReceive('get')->with($shipmentId)->andReturn(null);
        $cache->shouldReceive('hasUsableRoute')->with(null)->andReturn(false);

        $engine = new RouteProgressEngine($cache);
        $state = $engine->advance($this->fakeAssignment($shipmentId, $vehicleId), 60.0, 60);

        $this->assertNull($state);
    }

    private function fakeAssignment(string $shipmentId, string $vehicleId): Assignment
    {
        $assignment = new Assignment();
        $assignment->id = 'asg-1';
        $assignment->shipment_id = $shipmentId;
        $assignment->vehicle_id = $vehicleId;

        $shipment = new Shipment();
        $shipment->id = $shipmentId;
        $shipment->pickup_city = 'Munich';
        $shipment->delivery_city = 'Vienna';
        $assignment->setRelation('shipment', $shipment);

        $vehicle = new Vehicle();
        $vehicle->id = $vehicleId;
        $assignment->setRelation('vehicle', $vehicle);

        return $assignment;
    }
}
