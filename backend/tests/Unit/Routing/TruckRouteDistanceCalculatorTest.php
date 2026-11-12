<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\DTOs\RoutePlanningResult;
use App\Simulation\Routing\Application\Enums\PlanningStatus;
use App\Simulation\Routing\Application\Services\RoutePlanner;
use App\Simulation\Routing\Domain\Models\Route as DomainRoute;
use App\Simulation\Routing\Enums\RouteStatus;
use App\Simulation\Routing\Enums\TravelMode;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\ValueObjects\Distance;
use App\Simulation\Routing\ValueObjects\Duration;
use App\Simulation\Routing\ValueObjects\Polyline;
use App\Simulation\Routing\ValueObjects\RouteGeometry;
use Mockery;
use PHPUnit\Framework\TestCase;

class TruckRouteDistanceCalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_successful_truck_route_distance(): void
    {
        $planner = $this->mockPlannerWithDistance(500000); // 500km in meters

        $calculator = new TruckRouteDistanceCalculator($planner);
        $distance = $calculator->calculate(33.8869, 35.5131, 36.2021, 37.1343);

        $this->assertEqualsWithDelta(500.0, $distance, 0.001);
    }

    public function test_meters_to_kilometers_conversion(): void
    {
        $planner = $this->mockPlannerWithDistance(123456.78);

        $calculator = new TruckRouteDistanceCalculator($planner);
        $distance = $calculator->calculate(33.0, 35.0, 34.0, 36.0);

        $this->assertEqualsWithDelta(123.45678, $distance, 0.001);
    }

    public function test_zero_distance_supported(): void
    {
        $planner = $this->mockPlannerWithDistance(0);

        $calculator = new TruckRouteDistanceCalculator($planner);
        $distance = $calculator->calculate(33.8869, 35.5131, 33.8869, 35.5131);

        $this->assertEquals(0.0, $distance);
    }

    public function test_invalid_latitude_rejected(): void
    {
        $planner = Mockery::mock(RoutePlanner::class);
        $calculator = new TruckRouteDistanceCalculator($planner);

        $this->expectException(\InvalidArgumentException::class);
        $calculator->calculate(91.0, 35.0, 34.0, 36.0);
    }

    public function test_invalid_longitude_rejected(): void
    {
        $planner = Mockery::mock(RoutePlanner::class);
        $calculator = new TruckRouteDistanceCalculator($planner);

        $this->expectException(\InvalidArgumentException::class);
        $calculator->calculate(33.0, 181.0, 34.0, 36.0);
    }

    public function test_routing_failure_propagated(): void
    {
        $planner = Mockery::mock(RoutePlanner::class);
        $planner->shouldReceive('planRoute')
            ->once()
            ->andThrow(new RoutingProviderUnavailableException('GraphHopper unavailable'));

        $calculator = new TruckRouteDistanceCalculator($planner);

        $this->expectException(RoutingProviderUnavailableException::class);
        $calculator->calculate(33.0, 35.0, 34.0, 36.0);
    }

    public function test_failed_result_throws_exception(): void
    {
        $planner = Mockery::mock(RoutePlanner::class);
        $planner->shouldReceive('planRoute')
            ->once()
            ->andReturn(new RoutePlanningResult(
                planningId: 'test',
                route: null,
                providerName: 'graphhopper',
                executionTime: 100,
                planningStatus: PlanningStatus::FAILURE,
                metadata: [],
                warnings: [],
                diagnostics: [],
            ));

        $calculator = new TruckRouteDistanceCalculator($planner);

        $this->expectException(RoutingProviderUnavailableException::class);
        $calculator->calculate(33.0, 35.0, 34.0, 36.0);
    }

    public function test_uses_truck_profile(): void
    {
        $planner = Mockery::mock(RoutePlanner::class);
        $planner->shouldReceive('planRoute')
            ->once()
            ->withArgs(function (RoutePlanningRequest $req) {
                $vehicle = $req->context->vehicle;
                return $vehicle->freightClass === 'heavy_truck'
                    && $vehicle->hasTrailer === true
                    && $vehicle->weight === 25000.0;
            })
            ->andReturn($this->makeSuccessResult(100000));

        $calculator = new TruckRouteDistanceCalculator($planner);
        $distance = $calculator->calculate(33.0, 35.0, 34.0, 36.0);
        $this->assertEqualsWithDelta(100.0, $distance, 0.001);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function mockPlannerWithDistance(float $meters): RoutePlanner
    {
        $planner = Mockery::mock(RoutePlanner::class);
        $planner->shouldReceive('planRoute')
            ->once()
            ->andReturn($this->makeSuccessResult($meters));

        return $planner;
    }

    private function makeSuccessResult(float $distanceMeters): RoutePlanningResult
    {
        $route = new DomainRoute(
            id: 'route-1',
            origin: new Coordinate(33.0, 35.0),
            destination: new Coordinate(34.0, 36.0),
            waypoints: [],
            legs: [],
            geometry: new RouteGeometry(
                Polyline::fromCoordinates([new Coordinate(33.0, 35.0), new Coordinate(34.0, 36.0)]),
                [33.0, 35.0, 34.0, 36.0],
            ),
            distance: Distance::fromMeters($distanceMeters),
            duration: Duration::fromSeconds(3600),
            status: RouteStatus::COMPLETED,
            travelMode: TravelMode::DRIVING,
            createdAt: new \DateTime(),
            provider: 'graphhopper',
        );

        return new RoutePlanningResult(
            planningId: 'test',
            route: $route,
            providerName: 'graphhopper',
            executionTime: 50,
            planningStatus: PlanningStatus::SUCCESS,
            metadata: [],
            warnings: [],
            diagnostics: [],
        );
    }
}
