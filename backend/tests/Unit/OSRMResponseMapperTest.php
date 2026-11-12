<?php

namespace Tests\Unit;

use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Infrastructure\ResponseMappers\OSRMResponseMapper;
use App\Simulation\Routing\ValueObjects\Coordinate;
use App\Simulation\Routing\Enums\TravelMode;
use App\Simulation\Routing\Exceptions\InvalidRouteDataException;
use Tests\TestCase;

/**
 * OSRMResponseMapperTest
 * 
 * Unit tests for OSRMResponseMapper.
 */
class OSRMResponseMapperTest extends TestCase
{
    private OSRMResponseMapper $mapper;
    private Coordinate $origin;
    private Coordinate $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new OSRMResponseMapper();
        $this->origin = new Coordinate(52.5200, 13.4050);
        $this->destination = new Coordinate(48.8566, 2.3522);
    }

    /**
     * Test successful mapping of OSRM response.
     */
    public function test_successful_mapping()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => '_p~iF~ps|U_ulL~qC@',
                    'legs' => [
                        [
                            'steps' => [
                                [
                                    'maneuver' => [
                                        'type' => 'depart',
                                        'modifier' => 'left',
                                        'instruction' => 'Head north'
                                    ],
                                    'distance' => 123.4,
                                    'duration' => 15.6,
                                    'name' => 'Main St',
                                    'ref' => 'A1',
                                ]
                            ],
                            'distance' => 923456.7,
                            'duration' => 41400.5,
                            'summary' => 'A1, B2',
                        ]
                    ],
                    'distance' => 923456.7,
                    'duration' => 41400.5,
                    'weight' => 12345.6,
                    'weight_name' => 'routability',
                ]
            ],
            'waypoints' => [
                [
                    'name' => 'Berlin',
                    'location' => [13.4050, 52.5200],
                    'distance' => 5.2,
                ],
                [
                    'name' => 'Paris',
                    'location' => [2.3522, 48.8566],
                    'distance' => 3.1,
                ]
            ]
        ];

        $normalizedRoute = $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        // Verify NormalizedRoute structure
        $this->assertInstanceOf(\App\Simulation\Routing\Application\DTOs\NormalizedRoute::class, $normalizedRoute);
        $this->assertEquals($this->origin, $normalizedRoute->origin);
        $this->assertEquals($this->destination, $normalizedRoute->destination);
        $this->assertEquals(923456.7, $normalizedRoute->distance->meters);
        $this->assertEquals(41400.5, $normalizedRoute->duration->seconds);
        $this->assertIsArray($normalizedRoute->legs);
        $this->assertIsArray($normalizedRoute->steps);
        $this->assertIsArray($normalizedRoute->waypoints);
        $this->assertEquals('osrm', $normalizedRoute->metadata['provider']);
        $this->assertEquals('driving', $normalizedRoute->metadata['travel_mode']);
    }

    /**
     * Test validation with missing routes.
     */
    public function test_missing_routes_throws_exception()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [],
            'waypoints' => []
        ];

        $this->expectException(InvalidRouteDataException::class);
        $this->expectExceptionMessage('Invalid OSRM response format');

        $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );
    }

    /**
     * Test validation with invalid code.
     */
    public function test_invalid_code_throws_exception()
    {
        $osrmResponse = [
            'code' => 'NoRoute',
            'routes' => [
                [
                    'geometry' => '_p~iF~ps|U_ulL~qC@',
                    'legs' => [],
                    'distance' => 0,
                    'duration' => 0,
                ]
            ],
            'waypoints' => []
        ];

        $this->expectException(InvalidRouteDataException::class);
        $this->expectExceptionMessage('Invalid OSRM response format');

        $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );
    }

    /**
     * Test validation with missing waypoints.
     */
    public function test_missing_waypoints_throws_exception()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => '_p~iF~ps|U_ulL~qC@',
                    'legs' => [],
                    'distance' => 0,
                    'duration' => 0,
                ]
            ],
            // Missing waypoints key
        ];

        $this->expectException(InvalidRouteDataException::class);
        $this->expectExceptionMessage('Invalid OSRM response format');

        $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );
    }

    /**
     * Test empty geometry.
     */
    public function test_empty_geometry()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => '',
                    'legs' => [],
                    'distance' => 0,
                    'duration' => 0,
                ]
            ],
            'waypoints' => [
                [
                    'name' => 'Berlin',
                    'location' => [13.4050, 52.5200],
                    'distance' => 0,
                ]
            ]
        ];

        $normalizedRoute = $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        // Should still return NormalizedRoute with empty geometry
        $this->assertInstanceOf(\App\Simulation\Routing\Application\DTOs\NormalizedRoute::class, $normalizedRoute);
        $this->assertEquals(0, $normalizedRoute->distance->meters);
        $this->assertEquals(0, $normalizedRoute->duration->seconds);
    }

    /**
     * Test validateProviderResponse with valid response.
     */
    public function test_validate_provider_response_valid()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                ['geometry' => 'test', 'legs' => [], 'distance' => 0, 'duration' => 0]
            ],
            'waypoints' => []
        ];

        $this->assertTrue($this->mapper->validateProviderResponse($osrmResponse));
    }

    /**
     * Test validateProviderResponse with invalid code.
     */
    public function test_validate_provider_response_invalid_code()
    {
        $osrmResponse = [
            'code' => 'Error',
            'routes' => [
                ['geometry' => 'test', 'legs' => [], 'distance' => 0, 'duration' => 0]
            ],
            'waypoints' => []
        ];

        $this->assertFalse($this->mapper->validateProviderResponse($osrmResponse));
    }

    /**
     * Test validateProviderResponse with missing routes.
     */
    public function test_validate_provider_response_missing_routes()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [],
            'waypoints' => []
        ];

        $this->assertFalse($this->mapper->validateProviderResponse($osrmResponse));
    }

    /**
     * Test validateProviderResponse with missing waypoints.
     */
    public function test_validate_provider_response_missing_waypoints()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                ['geometry' => 'test', 'legs' => [], 'distance' => 0, 'duration' => 0]
            ],
        ];

        $this->assertFalse($this->mapper->validateProviderResponse($osrmResponse));
    }

    /**
     * Test getSupportedProvider.
     */
    public function test_get_supported_provider()
    {
        $this->assertEquals('osrm', $this->mapper->getSupportedProvider());
    }

    /**
     * Test legs mapping.
     */
    public function test_legs_mapping()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => '_p~iF~ps|U_ulL~qC@',
                    'legs' => [
                        [
                            'steps' => [],
                            'distance' => 5000.0,
                            'duration' => 300.0,
                            'summary' => 'A1',
                        ],
                        [
                            'steps' => [],
                            'distance' => 3000.0,
                            'duration' => 180.0,
                            'summary' => 'B2',
                        ]
                    ],
                    'distance' => 8000.0,
                    'duration' => 480.0,
                ]
            ],
            'waypoints' => [
                ['name' => 'A', 'location' => [13.4050, 52.5200], 'distance' => 0],
                ['name' => 'B', 'location' => [2.3522, 48.8566], 'distance' => 0],
            ]
        ];

        $normalizedRoute = $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        $this->assertCount(2, $normalizedRoute->legs);
        $this->assertEquals(5000.0, $normalizedRoute->legs[0]['distance']['meters']);
        $this->assertEquals(300.0, $normalizedRoute->legs[0]['duration']['seconds']);
        $this->assertEquals('A1', $normalizedRoute->legs[0]['summary']);
        $this->assertEquals(3000.0, $normalizedRoute->legs[1]['distance']['meters']);
        $this->assertEquals('B2', $normalizedRoute->legs[1]['summary']);
    }

    /**
     * Test steps mapping.
     */
    public function test_steps_mapping()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => '_p~iF~ps|U_ulL~qC@',
                    'legs' => [
                        [
                            'steps' => [
                                [
                                    'maneuver' => [
                                        'type' => 'turn',
                                        'modifier' => 'left',
                                        'instruction' => 'Turn left'
                                    ],
                                    'distance' => 100.0,
                                    'duration' => 10.0,
                                    'name' => 'Main St',
                                    'ref' => 'A1',
                                ]
                            ],
                            'distance' => 100.0,
                            'duration' => 10.0,
                        ]
                    ],
                    'distance' => 100.0,
                    'duration' => 10.0,
                ]
            ],
            'waypoints' => [
                ['name' => 'A', 'location' => [13.4050, 52.5200], 'distance' => 0],
                ['name' => 'B', 'location' => [2.3522, 48.8566], 'distance' => 0],
            ]
        ];

        $normalizedRoute = $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        $this->assertCount(1, $normalizedRoute->steps);
        $this->assertEquals('Turn left', $normalizedRoute->steps[0]['instruction']);
        $this->assertEquals('turn', $normalizedRoute->steps[0]['maneuver_type']);
        $this->assertEquals('left', $normalizedRoute->steps[0]['maneuver_modifier']);
        $this->assertEquals('Main St', $normalizedRoute->steps[0]['road_name']);
        $this->assertEquals('A1', $normalizedRoute->steps[0]['road_ref']);
    }

    /**
     * Test waypoints mapping.
     */
    public function test_waypoints_mapping()
    {
        $osrmResponse = [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => '_p~iF~ps|U_ulL~qC@',
                    'legs' => [],
                    'distance' => 0,
                    'duration' => 0,
                ]
            ],
            'waypoints' => [
                [
                    'name' => 'Berlin',
                    'location' => [13.4050, 52.5200],
                    'distance' => 5.2,
                ],
                [
                    'name' => 'Paris',
                    'location' => [2.3522, 48.8566],
                    'distance' => 3.1,
                ]
            ]
        ];

        $normalizedRoute = $this->mapper->mapToNormalizedRoute(
            $osrmResponse,
            $this->origin,
            $this->destination,
            [],
            TravelMode::DRIVING
        );

        $this->assertCount(2, $normalizedRoute->waypoints);
        $this->assertEquals('wp-0', $normalizedRoute->waypoints[0]['id']);
        $this->assertEquals('Berlin', $normalizedRoute->waypoints[0]['name']);
        $this->assertEquals(52.5200, $normalizedRoute->waypoints[0]['coordinate']['latitude']);
        $this->assertEquals(13.4050, $normalizedRoute->waypoints[0]['coordinate']['longitude']);
        $this->assertEquals(5.2, $normalizedRoute->waypoints[0]['distance']);
        $this->assertEquals(0, $normalizedRoute->waypoints[0]['sequence']);
    }
}
