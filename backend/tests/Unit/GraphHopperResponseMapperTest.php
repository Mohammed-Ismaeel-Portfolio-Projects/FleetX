<?php

namespace Tests\Unit;

use App\Simulation\Routing\Enums\TravelMode;
use App\Simulation\Routing\Exceptions\InvalidRouteDataException;
use App\Simulation\Routing\Infrastructure\ResponseMappers\GraphHopperResponseMapper;
use App\Simulation\Routing\ValueObjects\Coordinate;
use Tests\TestCase;

class GraphHopperResponseMapperTest extends TestCase
{
    private GraphHopperResponseMapper $mapper;
    private Coordinate $origin;
    private Coordinate $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new GraphHopperResponseMapper();
        $this->origin = new Coordinate(52.5200, 13.4050);
        $this->destination = new Coordinate(48.8566, 2.3522);
    }

    public function test_valid_mapping_with_encoded_polyline(): void
    {
        $response = [
            'paths' => [
                [
                    'distance' => 1054321.0,
                    'time' => 39600000, // ms → 39600 seconds
                    'points' => '_p~iF~ps|U_ulLnnqC',
                    'instructions' => [
                        [
                            'text' => 'Continue onto A1',
                            'distance' => 500.0,
                            'time' => 20000,
                            'sign' => 0,
                            'street_name' => 'A1',
                        ],
                        [
                            'text' => 'Arrive at destination',
                            'distance' => 0,
                            'time' => 0,
                            'sign' => 4,
                            'street_name' => '',
                        ],
                    ],
                    'bbox' => [2.35, 48.85, 13.40, 52.52],
                ],
            ],
        ];

        $normalized = $this->mapper->mapToNormalizedRoute(
            $response,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        $this->assertEquals(1054321.0, $normalized->distance->toMeters());
        $this->assertEquals(39600.0, $normalized->duration->toSeconds());
        $this->assertGreaterThanOrEqual(1, $normalized->geometry->getPolyline()->getCoordinateCount());
        $this->assertCount(2, $normalized->steps);
        $this->assertEquals('graphhopper', $normalized->metadata['provider']);
        $this->assertEquals('Continue onto A1', $normalized->steps[0]['instruction']);
        $this->assertEquals('arrive', $normalized->steps[1]['maneuver_type']);
    }

    public function test_valid_mapping_with_geojson_coordinates(): void
    {
        $response = [
            'paths' => [
                [
                    'distance' => 1000.0,
                    'time' => 60000,
                    'points' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [13.4050, 52.5200],
                            [10.0000, 50.0000],
                            [2.3522, 48.8566],
                        ],
                    ],
                    'instructions' => [],
                ],
            ],
        ];

        $normalized = $this->mapper->mapToNormalizedRoute(
            $response,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        $this->assertEquals(3, $normalized->geometry->getPolyline()->getCoordinateCount());
        $first = $normalized->geometry->getPolyline()->getCoordinate(0);
        $this->assertEqualsWithDelta(52.5200, $first->latitude, 0.0001);
        $this->assertEqualsWithDelta(13.4050, $first->longitude, 0.0001);
    }

    public function test_empty_geometry_returns_placeholder(): void
    {
        $response = [
            'paths' => [
                [
                    'distance' => 0,
                    'time' => 0,
                    'points' => '',
                    'instructions' => [],
                ],
            ],
        ];

        $normalized = $this->mapper->mapToNormalizedRoute(
            $response,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        $this->assertGreaterThanOrEqual(1, $normalized->geometry->getPolyline()->getCoordinateCount());
        $this->assertEquals(0.0, $normalized->distance->toMeters());
    }

    public function test_missing_paths_fails_validation(): void
    {
        $this->assertFalse($this->mapper->validateProviderResponse(['info' => []]));
        $this->assertFalse($this->mapper->validateProviderResponse(['paths' => []]));
    }

    public function test_missing_paths_throws_on_map(): void
    {
        $this->expectException(InvalidRouteDataException::class);

        $this->mapper->mapToNormalizedRoute(
            ['paths' => []],
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );
    }

    public function test_supported_provider_name(): void
    {
        $this->assertEquals('graphhopper', $this->mapper->getSupportedProvider());
    }
}
