<?php

namespace Tests\Feature\Shipment;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\TrackingState;
use App\Models\User;
use App\Models\Vehicle;
use App\Actions\Shipment\GetShipmentRouteAction;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * C2.7-C / C4.2-A3.2 / C5.5-C2 security / contract matrix for
 * GET /api/v1/client/shipments/{shipment}/tracking.
 */
class ClientShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $statusInTransit;

    private Status $assignmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();

        $this->statusInTransit = Status::firstOrCreate(
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE]
        );

        $this->assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/client/shipments/'.Str::uuid()->toString().'/tracking')
            ->assertUnauthorized();
    }

    public function test_linked_client_can_track_own_shipment(): void
    {
        $company = Company::factory()->create([
            'company_code' => 'ACME',
            'company_name' => 'ACME Logistics',
        ]);
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'shipment_number' => 'SHP-TRACK-001',
            'actual_pickup_date' => now()->toDateString(),
            'actual_delivery_date' => null,
            'pickup_phone' => 'SHOULD-NOT-LEAK',
            'pickup_contact' => 'Secret Contact',
            'pickup_address' => '99 Secret Street',
            'notes' => 'Internal ops note',
            'special_instructions' => 'Internal instructions',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shipment_id', $shipment->id)
            ->assertJsonPath('data.shipment_number', 'SHP-TRACK-001')
            ->assertJsonPath('data.status.name', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status.type', 'shipment')
            ->assertJsonPath('data.origin.city', 'Warsaw')
            ->assertJsonPath('data.origin.country', 'PL')
            ->assertJsonPath('data.origin.company', 'Pickup Co')
            ->assertJsonPath('data.origin.coordinates.latitude', 52.2297)
            ->assertJsonPath('data.origin.coordinates.longitude', 21.0122)
            ->assertJsonPath('data.destination.city', 'Krakow')
            ->assertJsonPath('data.destination.country', 'PL')
            ->assertJsonPath('data.destination.company', 'Delivery Co')
            ->assertJsonPath('data.destination.coordinates.latitude', 50.0647)
            ->assertJsonPath('data.destination.coordinates.longitude', 19.945)
            ->assertJsonPath('data.customer_company.id', $company->id)
            ->assertJsonPath('data.customer_company.company_code', 'ACME')
            ->assertJsonPath('data.customer_company.company_name', 'ACME Logistics')
            ->assertJsonPath('data.last_known_position', null)
            ->assertJsonPath('data.location_freshness.state', 'unavailable')
            ->assertJsonPath('data.location_freshness.recorded_at', null)
            ->assertJsonPath('data.location_freshness.age_seconds', 0);

        $data = $response->json('data');
        $this->assertArrayHasKey('planned_pickup_date', $data);
        $this->assertArrayHasKey('location_freshness', $data);
        $this->assertArrayHasKey('route', $data);
        $this->assertNull($data['route']);
        $this->assertArrayHasKey('planned_delivery_date', $data);
        $this->assertArrayHasKey('actual_pickup_date', $data);
        $this->assertArrayHasKey('actual_delivery_date', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('last_known_position', $data);
        $this->assertNotNull($data['planned_pickup_date']);
        $this->assertNotNull($data['actual_pickup_date']);
        $this->assertNull($data['actual_delivery_date']);

        $this->assertIsArray($data['milestones']);
        $this->assertCount(3, $data['milestones']);
        $this->assertSame('created', $data['milestones'][0]['key']);
        $this->assertArrayHasKey('occurred_at', $data['milestones'][0]);
        $this->assertSame('pickup', $data['milestones'][1]['key']);
        $this->assertArrayHasKey('planned_at', $data['milestones'][1]);
        $this->assertArrayHasKey('actual_at', $data['milestones'][1]);
        $this->assertSame('delivery', $data['milestones'][2]['key']);
        $this->assertArrayHasKey('planned_at', $data['milestones'][2]);
        $this->assertArrayHasKey('actual_at', $data['milestones'][2]);
        $this->assertNull($data['milestones'][2]['actual_at']);

        // Not historical status-transition events
        foreach ($data['milestones'] as $milestone) {
            $this->assertNotContains($milestone['key'], ['assigned', 'in_transit', 'at_customs', 'pending']);
            $this->assertArrayNotHasKey('state', $milestone);
        }
    }

    public function test_raw_status_name_is_preserved(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.status.name', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status.type', 'shipment');
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);

        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertForbidden();
    }

    public function test_dispatcher_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertForbidden();
    }

    public function test_unknown_id_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.Str::uuid()->toString().'/tracking')
            ->assertNotFound();
    }

    public function test_soft_deleted_shipment_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ['shipment_number' => 'SHP-DELETED-TRACK']);
        $shipment->delete();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertNotFound();
    }

    public function test_another_company_shipment_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeShipment($companyB, ['shipment_number' => 'SHP-FOREIGN-TRACK']);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$foreign->id.'/tracking');

        $response->assertNotFound();
        $this->assertStringNotContainsString('SHP-FOREIGN-TRACK', $response->getContent());
        $this->assertStringNotContainsString($foreign->id, (string) $response->json('data'));
    }

    public function test_customer_company_id_query_is_rejected_with_422(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $shipment = $this->makeShipment($companyA);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking?customer_company_id='.$companyB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_company_id']);
    }

    public function test_company_id_query_is_rejected_with_422(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $shipment = $this->makeShipment($companyA);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking?company_id='.$companyB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_ops_probe_query_params_are_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        foreach (['driver_id=1', 'vehicle_id=1', 'assignment_id=1', 'tenant_id=1'] as $query) {
            $this->actingAs($client, 'api')
                ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking?'.$query)
                ->assertStatus(422);
        }
    }

    public function test_response_excludes_forbidden_and_top_level_gps_fields(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'pickup_phone' => 'SHOULD-NOT-LEAK',
            'pickup_contact' => 'Secret Contact',
            'pickup_address' => '99 Secret Street',
            'delivery_phone' => 'SHOULD-NOT-LEAK-2',
            'delivery_contact' => 'Secret Delivery',
            'delivery_address' => '100 Secret Ave',
            'notes' => 'Internal ops note',
            'special_instructions' => 'Internal instructions',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk();
        $item = $response->json('data');

        foreach ([
            'updated_at',
            'status_changed_at',
            'pickup_phone',
            'pickup_contact',
            'pickup_address',
            'delivery_phone',
            'delivery_contact',
            'delivery_address',
            'notes',
            'special_instructions',
            'driver',
            'driver_id',
            'vehicle',
            'vehicle_id',
            'plate',
            'plate_number',
            'latitude',
            'longitude',
            'coordinates',
            'gps',
            'location',
            'eta',
            'tracking_logs',
            'tracking_states',
            'route_geometry',
            'dispatcher',
            'active_assignment',
            'assignments',
            'created_by',
            'updated_by',
            'deleted_at',
            'vehicles',
            'fleet',
            'engine',
            'speed',
            'heading',
            'accuracy',
            'movement_status',
            'online_status',
            'signal_quality',
            'trailer_id',
            'assignment_id',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item, $forbidden);
        }

        $this->assertArrayHasKey('last_known_position', $item);
        $this->assertNull($item['last_known_position']);
        $this->assertArrayHasKey('route', $item);
        $this->assertNull($item['route']);
        $this->assertArrayHasKey('coordinates', $item['origin']);
        $this->assertArrayHasKey('coordinates', $item['destination']);
        $this->assertIsFloat($item['origin']['coordinates']['latitude']);
        $this->assertIsFloat($item['origin']['coordinates']['longitude']);
        $this->assertGreaterThanOrEqual(-90.0, $item['origin']['coordinates']['latitude']);
        $this->assertLessThanOrEqual(90.0, $item['origin']['coordinates']['latitude']);
        $this->assertGreaterThanOrEqual(-180.0, $item['origin']['coordinates']['longitude']);
        $this->assertLessThanOrEqual(180.0, $item['origin']['coordinates']['longitude']);

        $this->assertStringNotContainsString('SHOULD-NOT-LEAK', $response->getContent());
        $this->assertStringNotContainsString('99 Secret Street', $response->getContent());
        $this->assertStringNotContainsString('Internal ops note', $response->getContent());
        $this->assertStringNotContainsString('live-fleet', $response->getContent());
    }

    public function test_unknown_pickup_and_delivery_cities_yield_null_coordinates(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'pickup_city' => 'Atlantis-Not-A-Hub',
            'delivery_city' => 'El Dorado Fake City',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.origin.city', 'Atlantis-Not-A-Hub')
            ->assertJsonPath('data.destination.city', 'El Dorado Fake City')
            ->assertJsonPath('data.origin.coordinates', null)
            ->assertJsonPath('data.destination.coordinates', null);

        $this->assertNull($response->json('data.origin.coordinates'));
        $this->assertNull($response->json('data.destination.coordinates'));
    }

    public function test_planned_route_cache_origin_and_destination_are_preferred(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'pickup_city' => 'Warsaw',
            'delivery_city' => 'Krakow',
        ]);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'origin' => ['latitude' => 48.8566, 'longitude' => 2.3522],
            'destination' => ['latitude' => 41.9028, 'longitude' => 12.4964],
            'coordinates' => [
                ['latitude' => 48.8566, 'longitude' => 2.3522],
                ['latitude' => 41.9028, 'longitude' => 12.4964],
            ],
            'api_payload' => [
                'route' => [
                    'id' => 'cached-route',
                    'origin' => ['latitude' => 48.8566, 'longitude' => 2.3522],
                    'destination' => ['latitude' => 41.9028, 'longitude' => 12.4964],
                    'geometry' => [
                        'coordinates' => [
                            ['latitude' => 48.8566, 'longitude' => 2.3522],
                            ['latitude' => 41.9028, 'longitude' => 12.4964],
                        ],
                    ],
                    'eta' => '2099-01-01 00:00:00',
                    'provider' => 'graphhopper',
                ],
                'metadata' => [
                    'provider_name' => 'graphhopper',
                    'deviation_distance' => 999,
                ],
            ],
            'provider' => 'graphhopper',
            'pickup_city' => 'Warsaw',
            'delivery_city' => 'Krakow',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.origin.city', 'Warsaw')
            ->assertJsonPath('data.destination.city', 'Krakow')
            ->assertJsonPath('data.origin.coordinates.latitude', 48.8566)
            ->assertJsonPath('data.origin.coordinates.longitude', 2.3522)
            ->assertJsonPath('data.destination.coordinates.latitude', 41.9028)
            ->assertJsonPath('data.destination.coordinates.longitude', 12.4964)
            ->assertJsonPath('data.route.coordinates.0.latitude', 48.8566)
            ->assertJsonPath('data.route.coordinates.0.longitude', 2.3522)
            ->assertJsonPath('data.route.coordinates.1.latitude', 41.9028)
            ->assertJsonPath('data.route.coordinates.1.longitude', 12.4964);

        $data = $response->json('data');
        $this->assertArrayHasKey('route', $data);
        $this->assertSame(
            ['latitude', 'longitude'],
            array_keys($data['route']['coordinates'][0])
        );
        $this->assertArrayNotHasKey('eta', $data);
        $this->assertArrayNotHasKey('provider', $data);
        $this->assertArrayNotHasKey('provider', $data['origin']);
        $this->assertArrayNotHasKey('eta', $data['route']);
        $this->assertArrayNotHasKey('provider', $data['route']);
        $this->assertArrayNotHasKey('encoded', $data['route']);
        $this->assertStringNotContainsString('graphhopper', $response->getContent());
        $this->assertStringNotContainsString('deviation_distance', $response->getContent());
        $this->assertStringNotContainsString('2099-01-01', $response->getContent());
    }

    public function test_malformed_cached_coordinates_fall_back_to_known_city(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'pickup_city' => 'Warsaw',
            'delivery_city' => 'Krakow',
        ]);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'origin' => ['latitude' => 91.0, 'longitude' => 21.0],
            'destination' => ['latitude' => 'not-a-number', 'longitude' => 19.0],
            'coordinates' => [],
            'api_payload' => ['route' => []],
            'provider' => 'graphhopper',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.origin.coordinates.latitude', 52.2297)
            ->assertJsonPath('data.origin.coordinates.longitude', 21.0122)
            ->assertJsonPath('data.destination.coordinates.latitude', 50.0647)
            ->assertJsonPath('data.destination.coordinates.longitude', 19.945)
            ->assertJsonPath('data.route', null);
    }

    public function test_foreign_company_tracking_still_returns_404_without_exposing_coords(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeShipment($companyB, [
            'pickup_city' => 'Warsaw',
            'delivery_city' => 'Krakow',
        ]);

        Cache::forever('routing:planned_route:'.$foreign->id, [
            'shipment_id' => $foreign->id,
            'origin' => ['latitude' => 1.0, 'longitude' => 2.0],
            'destination' => ['latitude' => 3.0, 'longitude' => 4.0],
            'coordinates' => [
                ['latitude' => 1.0, 'longitude' => 2.0],
                ['latitude' => 3.0, 'longitude' => 4.0],
            ],
            'api_payload' => [
                'route' => [
                    'id' => 'secret',
                    'geometry' => [
                        'coordinates' => [
                            ['latitude' => 1.0, 'longitude' => 2.0],
                            ['latitude' => 3.0, 'longitude' => 4.0],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$foreign->id.'/tracking');

        $response->assertNotFound();
        $this->assertStringNotContainsString('"latitude":1', $response->getContent());
        $this->assertStringNotContainsString('"longitude":2', $response->getContent());
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_client_tracking_does_not_invoke_get_shipment_route_action(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $routeAction = Mockery::mock(GetShipmentRouteAction::class);
        $routeAction->shouldNotReceive('execute');
        $this->app->instance(GetShipmentRouteAction::class, $routeAction);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.origin.coordinates.latitude', 52.2297);
    }

    public function test_location_freshness_unavailable_when_no_position(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.last_known_position', null)
            ->assertJsonPath('data.location_freshness.state', 'unavailable')
            ->assertJsonPath('data.location_freshness.recorded_at', null)
            ->assertJsonPath('data.location_freshness.age_seconds', 0);
    }

    public function test_location_freshness_is_fresh_under_five_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $assignment = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($assignment, $shipment, [
            'latitude' => 52.2297000,
            'longitude' => 21.0122000,
            'last_update_at' => Carbon::parse('2026-08-15 11:56:00'),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.location_freshness.state', 'fresh')
            ->assertJsonPath('data.location_freshness.age_seconds', 240);

        $this->assertSame(
            $response->json('data.last_known_position.recorded_at'),
            $response->json('data.location_freshness.recorded_at')
        );
        $this->assertIsInt($response->json('data.location_freshness.age_seconds'));
    }

    public function test_location_freshness_is_stale_at_exact_five_minute_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $assignment = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($assignment, $shipment, [
            'last_update_at' => Carbon::parse('2026-08-15 11:55:00'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.location_freshness.state', 'stale')
            ->assertJsonPath('data.location_freshness.age_seconds', 300);
    }

    public function test_location_freshness_is_stale_between_five_and_thirty_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $assignment = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($assignment, $shipment, [
            'last_update_at' => Carbon::parse('2026-08-15 11:40:00'),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.location_freshness.state', 'stale')
            ->assertJsonPath('data.location_freshness.age_seconds', 1200);

        $this->assertSame(
            $response->json('data.last_known_position.recorded_at'),
            $response->json('data.location_freshness.recorded_at')
        );
    }

    public function test_location_freshness_is_stale_at_exact_thirty_minute_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $assignment = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($assignment, $shipment, [
            'last_update_at' => Carbon::parse('2026-08-15 11:30:00'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.location_freshness.state', 'stale')
            ->assertJsonPath('data.location_freshness.age_seconds', 1800);
    }

    public function test_location_freshness_is_very_stale_after_thirty_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $assignment = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($assignment, $shipment, [
            'last_update_at' => Carbon::parse('2026-08-15 11:29:59'),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.location_freshness.state', 'very_stale')
            ->assertJsonPath('data.location_freshness.age_seconds', 1801);

        $this->assertIsInt($response->json('data.location_freshness.age_seconds'));
        $this->assertSame(
            $response->json('data.last_known_position.recorded_at'),
            $response->json('data.location_freshness.recorded_at')
        );
    }

    public function test_foreign_company_does_not_leak_location_freshness(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeShipment($companyB);
        $assignment = $this->makeActiveAssignment($foreign);
        $this->makeTrackingState($assignment, $foreign, [
            'last_update_at' => Carbon::parse('2026-08-15 11:59:00'),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$foreign->id.'/tracking');

        $response->assertNotFound();
        $this->assertStringNotContainsString('location_freshness', $response->getContent());
        $this->assertStringNotContainsString('"state":"fresh"', $response->getContent());
    }

    public function test_valid_cached_planned_route_returns_sanitized_coordinates(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'origin' => ['latitude' => 52.2297, 'longitude' => 21.0122],
            'destination' => ['latitude' => 50.0647, 'longitude' => 19.945],
            'coordinates' => [
                ['latitude' => 52.2297, 'longitude' => 21.0122],
                ['latitude' => 51.5, 'longitude' => 20.5],
                ['latitude' => 50.0647, 'longitude' => 19.945],
            ],
            'api_payload' => [
                'route' => [
                    'id' => 'ops-route-id',
                    'eta' => '2099-12-31 23:59:59',
                    'provider' => 'graphhopper',
                    'distance' => ['meters' => 99999],
                    'duration' => ['seconds' => 3600],
                    'geometry' => [
                        'encoded' => 'ENCODED_POLYLINE_SECRET',
                        'coordinates' => [
                            ['latitude' => 52.2297, 'longitude' => 21.0122],
                            ['latitude' => 50.0647, 'longitude' => 19.945],
                        ],
                    ],
                    'legs' => [['steps' => []]],
                    'steps' => [['instruction' => 'turn left']],
                    'waypoints' => [['name' => 'hub']],
                ],
                'metadata' => [
                    'provider_name' => 'graphhopper',
                    'deviation_distance' => 42.5,
                ],
            ],
            'provider' => 'graphhopper',
            'route_status' => 'OFF_ROUTE',
            'deviation_distance' => 42.5,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.route.coordinates.0.latitude', 52.2297)
            ->assertJsonPath('data.route.coordinates.0.longitude', 21.0122)
            ->assertJsonPath('data.route.coordinates.1.latitude', 51.5)
            ->assertJsonPath('data.route.coordinates.1.longitude', 20.5)
            ->assertJsonPath('data.route.coordinates.2.latitude', 50.0647)
            ->assertJsonPath('data.route.coordinates.2.longitude', 19.945)
            ->assertJsonPath('data.origin.coordinates.latitude', 52.2297)
            ->assertJsonPath('data.destination.coordinates.latitude', 50.0647)
            ->assertJsonPath('data.location_freshness.state', 'unavailable');

        $route = $response->json('data.route');
        $this->assertSame(['coordinates'], array_keys($route));
        $this->assertCount(3, $route['coordinates']);
        $this->assertSame(['latitude', 'longitude'], array_keys($route['coordinates'][0]));

        $body = $response->getContent();
        $this->assertStringNotContainsString('ENCODED_POLYLINE_SECRET', $body);
        $this->assertStringNotContainsString('graphhopper', $body);
        $this->assertStringNotContainsString('ops-route-id', $body);
        $this->assertStringNotContainsString('2099-12-31', $body);
        $this->assertStringNotContainsString('deviation_distance', $body);
        $this->assertStringNotContainsString('OFF_ROUTE', $body);
        $this->assertStringNotContainsString('turn left', $body);
        $this->assertStringNotContainsString('"legs"', $body);
        $this->assertStringNotContainsString('"provider"', $body);
        $this->assertStringNotContainsString('"eta"', $body);
        $this->assertStringNotContainsString('"distance"', $body);
        $this->assertStringNotContainsString('"duration"', $body);
    }

    public function test_nested_api_payload_geometry_coordinates_are_supported(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'coordinates' => [],
            'origin' => ['latitude' => 52.0, 'longitude' => 21.0],
            'destination' => ['latitude' => 50.0, 'longitude' => 19.0],
            'api_payload' => [
                'route' => [
                    'geometry' => [
                        'encoded' => 'NESTED_ENCODED',
                        'coordinates' => [
                            ['lat' => 52.1, 'lon' => 21.1],
                            ['lat' => 50.1, 'lng' => 19.1],
                        ],
                    ],
                    'provider' => 'graphhopper',
                    'eta' => '2099-01-01 00:00:00',
                ],
            ],
            'provider' => 'graphhopper',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.route.coordinates.0.latitude', 52.1)
            ->assertJsonPath('data.route.coordinates.0.longitude', 21.1)
            ->assertJsonPath('data.route.coordinates.1.latitude', 50.1)
            ->assertJsonPath('data.route.coordinates.1.longitude', 19.1);

        $this->assertSame(
            ['latitude', 'longitude'],
            array_keys($response->json('data.route.coordinates.0'))
        );
        $this->assertStringNotContainsString('NESTED_ENCODED', $response->getContent());
        $this->assertStringNotContainsString('graphhopper', $response->getContent());
    }

    public function test_route_is_null_on_cache_miss(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.route', null);
    }

    public function test_route_is_null_when_cached_coordinates_empty(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'coordinates' => [],
            'api_payload' => ['route' => ['geometry' => ['coordinates' => []]]],
            'provider' => 'graphhopper',
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.route', null);
    }

    public function test_route_is_null_for_one_point_geometry(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'coordinates' => [
                ['latitude' => 52.2297, 'longitude' => 21.0122],
            ],
            'api_payload' => [
                'route' => [
                    'geometry' => [
                        'coordinates' => [
                            ['latitude' => 52.2297, 'longitude' => 21.0122],
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.route', null);
    }

    public function test_route_is_null_when_any_coordinate_is_malformed(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'coordinates' => [
                ['latitude' => 52.2297, 'longitude' => 21.0122],
                ['latitude' => 'not-a-number', 'longitude' => 19.945],
            ],
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.route', null);
    }

    public function test_route_is_null_when_coordinates_are_out_of_range(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'coordinates' => [
                ['latitude' => 52.2297, 'longitude' => 21.0122],
                ['latitude' => 91.0, 'longitude' => 19.945],
            ],
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()->assertJsonPath('data.route', null);
        $this->assertStringNotContainsString('"latitude":91', $response->getContent());
    }

    public function test_foreign_company_does_not_read_planned_route_cache_or_leak_route(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $foreign = $this->makeShipment($companyB);

        Cache::forever('routing:planned_route:'.$foreign->id, [
            'shipment_id' => $foreign->id,
            'coordinates' => [
                ['latitude' => 11.11, 'longitude' => 22.22],
                ['latitude' => 33.33, 'longitude' => 44.44],
            ],
            'api_payload' => [
                'route' => [
                    'geometry' => [
                        'encoded' => 'FOREIGN_POLYLINE',
                        'coordinates' => [
                            ['latitude' => 11.11, 'longitude' => 22.22],
                            ['latitude' => 33.33, 'longitude' => 44.44],
                        ],
                    ],
                    'eta' => '2099-06-01 12:00:00',
                    'provider' => 'graphhopper',
                ],
            ],
            'provider' => 'graphhopper',
        ]);

        $cache = Mockery::mock(PlannedRouteCache::class);
        $cache->shouldNotReceive('get');
        $this->app->instance(PlannedRouteCache::class, $cache);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$foreign->id.'/tracking');

        $response->assertNotFound();
        $body = $response->getContent();
        $this->assertStringNotContainsString('11.11', $body);
        $this->assertStringNotContainsString('22.22', $body);
        $this->assertStringNotContainsString('FOREIGN_POLYLINE', $body);
        $this->assertStringNotContainsString('"route"', $body);
        $this->assertStringNotContainsString('graphhopper', $body);
    }

    public function test_client_route_projection_does_not_invoke_get_shipment_route_action(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'coordinates' => [
                ['latitude' => 52.25, 'longitude' => 21.05],
                ['latitude' => 50.15, 'longitude' => 19.15],
            ],
            'origin' => ['latitude' => 52.25, 'longitude' => 21.05],
            'destination' => ['latitude' => 50.15, 'longitude' => 19.15],
            'api_payload' => ['route' => ['geometry' => ['coordinates' => []]]],
        ]);

        $routeAction = Mockery::mock(GetShipmentRouteAction::class);
        $routeAction->shouldNotReceive('execute');
        $this->app->instance(GetShipmentRouteAction::class, $routeAction);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.route.coordinates.0.latitude', 52.25)
            ->assertJsonPath('data.route.coordinates.1.longitude', 19.15)
            ->assertJsonPath('data.origin.coordinates.latitude', 52.25)
            ->assertJsonPath('data.location_freshness.state', 'unavailable');
    }

    public function test_final_contract_allowlist_and_field_types_with_poisoned_ops_cache(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        $company = Company::factory()->create([
            'company_code' => 'SAFECO',
            'company_name' => 'Safe Co',
        ]);
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'shipment_number' => 'SHP-C25-001',
            'pickup_city' => 'Warsaw',
            'delivery_city' => 'Krakow',
            'pickup_phone' => 'OPS-PHONE-LEAK',
            'notes' => 'OPS-NOTE-LEAK',
            'special_instructions' => 'OPS-INSTR-LEAK',
        ]);
        $assignment = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($assignment, $shipment, [
            'latitude' => 52.2297000,
            'longitude' => 21.0122000,
            'speed' => 88.5,
            'heading' => 123.4,
            'accuracy' => 4.2,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
            'last_update_at' => Carbon::parse('2026-08-15 11:50:00'),
        ]);

        Cache::forever('routing:planned_route:'.$shipment->id, [
            'shipment_id' => $shipment->id,
            'origin' => ['latitude' => 52.2297, 'longitude' => 21.0122],
            'destination' => ['latitude' => 50.0647, 'longitude' => 19.945],
            'coordinates' => [
                ['latitude' => 52.2297, 'longitude' => 21.0122],
                ['latitude' => 51.1, 'longitude' => 20.2],
                ['latitude' => 50.0647, 'longitude' => 19.945],
            ],
            'api_payload' => [
                'route' => [
                    'id' => 'internal-route-uuid',
                    'eta' => '2099-12-31 23:59:59',
                    'provider' => 'graphhopper',
                    'distance' => ['meters' => 12345],
                    'duration' => ['seconds' => 6789],
                    'geometry' => [
                        'encoded' => 'GEOMETRY_ENCODED_SECRET',
                        'coordinates' => [
                            ['latitude' => 52.2297, 'longitude' => 21.0122],
                            ['latitude' => 50.0647, 'longitude' => 19.945],
                        ],
                    ],
                    'legs' => [['distance' => 1]],
                    'steps' => [['instruction' => 'turn right']],
                    'waypoints' => [['name' => 'secret-hub']],
                ],
                'metadata' => [
                    'provider_name' => 'graphhopper',
                    'execution_time_ms' => 42,
                    'deviation_distance' => 99.9,
                    'engine_ops' => true,
                    'simulation' => ['tick' => 7],
                ],
            ],
            'provider' => 'graphhopper',
            'route_status' => 'OFF_ROUTE',
            'deviation_distance' => 99.9,
            'is_reroute' => true,
            'frozen_at' => '2099-01-01T00:00:00Z',
        ]);

        $routeAction = Mockery::mock(GetShipmentRouteAction::class);
        $routeAction->shouldNotReceive('execute');
        $this->app->instance(GetShipmentRouteAction::class, $routeAction);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertClientTrackingAllowlist($data);
        $this->assertValidEndpointCoordinates($data['origin']['coordinates']);
        $this->assertValidEndpointCoordinates($data['destination']['coordinates']);
        $this->assertValidLastKnownPosition($data['last_known_position']);
        $this->assertValidLocationFreshness($data['location_freshness'], $data['last_known_position']);
        $this->assertValidClientRoute($data['route']);
        $this->assertForbiddenOpsArtifactsAbsent($response->getContent());
        $this->assertForbiddenTelemetryAbsent($data);

        $this->assertSame('SHP-C25-001', $data['shipment_number']);
        $this->assertSame(['city', 'country', 'company', 'coordinates'], array_keys($data['origin']));
        $this->assertSame(['city', 'country', 'company', 'coordinates'], array_keys($data['destination']));
        $this->assertSame(52.2297, $data['origin']['coordinates']['latitude']);
        $this->assertSame(50.0647, $data['destination']['coordinates']['latitude']);
        $this->assertSame('stale', $data['location_freshness']['state']);
        $this->assertSame(600, $data['location_freshness']['age_seconds']);
        $this->assertCount(3, $data['route']['coordinates']);
        $this->assertStringNotContainsString('OPS-PHONE-LEAK', $response->getContent());
        $this->assertStringNotContainsString('OPS-NOTE-LEAK', $response->getContent());
    }

    public function test_final_contract_null_route_and_unavailable_freshness_shapes(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, [
            'pickup_city' => 'Warsaw',
            'delivery_city' => 'Krakow',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertClientTrackingAllowlist($data);
        $this->assertValidEndpointCoordinates($data['origin']['coordinates']);
        $this->assertValidEndpointCoordinates($data['destination']['coordinates']);
        $this->assertNull($data['last_known_position']);
        $this->assertValidLocationFreshness($data['location_freshness'], null);
        $this->assertNull($data['route']);
        $this->assertSame('unavailable', $data['location_freshness']['state']);
        $this->assertNull($data['location_freshness']['recorded_at']);
        $this->assertSame(0, $data['location_freshness']['age_seconds']);
    }

    public function test_final_tenancy_matrix_preserves_404_and_forbids_cache_read(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $own = $this->makeShipment($companyA);
        $foreign = $this->makeShipment($companyB);
        $deleted = $this->makeShipment($companyA);
        $deleted->delete();

        Cache::forever('routing:planned_route:'.$foreign->id, [
            'shipment_id' => $foreign->id,
            'coordinates' => [
                ['latitude' => 1.23, 'longitude' => 4.56],
                ['latitude' => 7.89, 'longitude' => 0.12],
            ],
            'provider' => 'graphhopper',
            'api_payload' => [
                'route' => [
                    'eta' => '2099-01-01 00:00:00',
                    'geometry' => ['encoded' => 'FOREIGN_ENCODED'],
                ],
            ],
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$own->id.'/tracking')
            ->assertOk();

        $cache = Mockery::mock(PlannedRouteCache::class);
        $cache->shouldNotReceive('get');
        $this->app->instance(PlannedRouteCache::class, $cache);

        $foreignResponse = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$foreign->id.'/tracking');
        $foreignResponse->assertNotFound();
        $this->assertStringNotContainsString('1.23', $foreignResponse->getContent());
        $this->assertStringNotContainsString('FOREIGN_ENCODED', $foreignResponse->getContent());
        $this->assertStringNotContainsString('location_freshness', $foreignResponse->getContent());
        $this->assertStringNotContainsString('"route"', $foreignResponse->getContent());

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.Str::uuid()->toString().'/tracking')
            ->assertNotFound();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$deleted->id.'/tracking')
            ->assertNotFound();

        $unlinked = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $this->actingAs($unlinked, 'api')
            ->getJson('/api/v1/client/shipments/'.$own->id.'/tracking')
            ->assertForbidden();
    }

    public function test_ops_tracking_endpoints_remain_forbidden_for_client(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/tracking/live-fleet')
            ->assertForbidden();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/tracking-logs?shipment_id='.$shipment->id)
            ->assertForbidden();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/tracking-states?shipment_id='.$shipment->id)
            ->assertForbidden();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/shipments/'.$shipment->id.'/route')
            ->assertForbidden();
    }

    public function test_last_known_position_is_null_when_no_telemetry_exists(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.last_known_position', null);
    }

    public function test_active_assignment_tracking_state_wins_over_historical_state(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => 50.0000000,
            'longitude' => 19.0000000,
            'last_update_at' => now()->subHour(),
            'speed' => 99,
            'heading' => 180,
            'movement_status' => 'Moving',
        ]);

        $active = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($active, $shipment, [
            'latitude' => 52.2297000,
            'longitude' => 21.0122000,
            'last_update_at' => now()->subMinutes(5),
            'speed' => 42.5,
            'heading' => 90,
            'driver_id' => $active->driver_id,
            'vehicle_id' => $active->vehicle_id,
            'accuracy' => 3.5,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 52.2297)
            ->assertJsonPath('data.last_known_position.longitude', 21.0122);

        $position = $response->json('data.last_known_position');
        $this->assertSame(
            ['latitude', 'longitude', 'recorded_at'],
            array_keys($position)
        );
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $position['recorded_at']);
        $this->assertForbiddenTelemetryAbsent($response->json('data'));
    }

    public function test_latest_shipment_tracking_state_is_used_when_no_active_assignment_state(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $older = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($older, $shipment, [
            'latitude' => 51.1000000,
            'longitude' => 17.1000000,
            'last_update_at' => now()->subHours(2),
        ]);

        $newer = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($newer, $shipment, [
            'latitude' => 52.4064000,
            'longitude' => 16.9252000,
            'last_update_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 52.4064)
            ->assertJsonPath('data.last_known_position.longitude', 16.9252);

        $this->assertSame(
            ['latitude', 'longitude', 'recorded_at'],
            array_keys($response->json('data.last_known_position'))
        );
    }

    public function test_latest_tracking_log_is_used_when_no_tracking_state_exists(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 50.0614000,
            'longitude' => 19.9383000,
            'speed' => 30,
            'heading' => 45,
            'accuracy' => 8,
            'recorded_at' => now()->subHours(3),
        ]);

        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 54.3520000,
            'longitude' => 18.6466000,
            'speed' => 55,
            'heading' => 10,
            'accuracy' => 4,
            'recorded_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 54.352)
            ->assertJsonPath('data.last_known_position.longitude', 18.6466);

        $position = $response->json('data.last_known_position');
        $this->assertSame(['latitude', 'longitude', 'recorded_at'], array_keys($position));
        $this->assertForbiddenTelemetryAbsent($response->json('data'));
    }

    public function test_priority_one_requires_matching_shipment_id_on_tracking_state(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $otherShipment = $this->makeShipment($company, ['shipment_number' => 'SHP-OTHER-SCOPE']);

        $active = $this->makeActiveAssignment($shipment);

        // Intentionally mismatched shipment_id (assignment belongs to $shipment).
        TrackingState::query()->create([
            'assignment_id' => $active->id,
            'shipment_id' => $otherShipment->id,
            'vehicle_id' => $active->vehicle_id,
            'driver_id' => $active->driver_id,
            'trailer_id' => null,
            'latitude' => 48.8566000,
            'longitude' => 2.3522000,
            'speed' => 10,
            'heading' => 0,
            'accuracy' => 5,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
            'last_update_at' => now(),
        ]);

        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 53.1325000,
            'longitude' => 23.1688000,
            'recorded_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 53.1325)
            ->assertJsonPath('data.last_known_position.longitude', 23.1688);

        $this->assertNotEquals(48.8566, $response->json('data.last_known_position.latitude'));
    }

    public function test_foreign_company_telemetry_is_not_exposed_on_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $foreign = $this->makeShipment($companyB, ['shipment_number' => 'SHP-FOREIGN-GPS']);

        $assignment = $this->makeActiveAssignment($foreign);
        $this->makeTrackingState($assignment, $foreign, [
            'latitude' => 41.9028000,
            'longitude' => 12.4964000,
            'last_update_at' => now(),
        ]);

        $response = $this->actingAs($clientA, 'api')
            ->getJson('/api/v1/client/shipments/'.$foreign->id.'/tracking');

        $response->assertNotFound();
        $this->assertStringNotContainsString('41.9028', $response->getContent());
        $this->assertStringNotContainsString('12.4964', $response->getContent());
        $this->assertStringNotContainsString('SHP-FOREIGN-GPS', $response->getContent());
        $this->assertStringNotContainsString((string) $foreign->id, (string) $response->json('data'));
    }

    public function test_last_known_position_nested_allowlist_excludes_ops_telemetry(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);
        $active = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($active, $shipment, [
            'latitude' => 52.5200000,
            'longitude' => 13.4050000,
            'last_update_at' => now()->subMinute(),
            'speed' => 77.7,
            'heading' => 123.4,
            'accuracy' => 2.2,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk();
        $data = $response->json('data');
        $position = $data['last_known_position'];

        $this->assertSame(['latitude', 'longitude', 'recorded_at'], array_keys($position));
        $this->assertSame(52.52, $position['latitude']);
        $this->assertSame(13.405, $position['longitude']);
        $this->assertIsFloat($position['latitude']);
        $this->assertIsFloat($position['longitude']);
        $this->assertIsString($position['recorded_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $position['recorded_at']);
        $this->assertForbiddenTelemetryAbsent($data);
        $this->assertArrayNotHasKey('latitude', $data);
        $this->assertArrayNotHasKey('longitude', $data);
        $this->assertArrayNotHasKey('coordinates', $data);
        $this->assertArrayNotHasKey('gps', $data);
    }

    public function test_active_assignment_without_state_falls_through_to_priority_two(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->makeActiveAssignment($shipment);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => 51.1079000,
            'longitude' => 17.0385000,
            'last_update_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 51.1079)
            ->assertJsonPath('data.last_known_position.longitude', 17.0385);
    }

    public function test_no_active_assignment_uses_priority_two_or_three(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => 50.2649000,
            'longitude' => 19.0238000,
            'last_update_at' => now()->subMinutes(15),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 50.2649)
            ->assertJsonPath('data.last_known_position.longitude', 19.0238);
    }

    public function test_soft_deleted_assignment_is_not_treated_as_active_priority_one(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $wasActive = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($wasActive, $shipment, [
            'latitude' => 52.2297000,
            'longitude' => 21.0122000,
            'last_update_at' => now()->subHours(2),
        ]);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => 54.5189000,
            'longitude' => 18.5305000,
            'last_update_at' => now()->subMinutes(5),
        ]);

        $wasActive->delete();

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        // Soft-deleted assignment is not Priority 1, so newer historical state wins via Priority 2.
        $response->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 54.5189)
            ->assertJsonPath('data.last_known_position.longitude', 18.5305);
    }

    public function test_invalid_priority_one_coordinates_fall_through_to_priority_two(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $active = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($active, $shipment, [
            'latitude' => 91.0000000,
            'longitude' => 21.0122000,
            'last_update_at' => now(),
        ]);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => 52.2297000,
            'longitude' => 21.0122000,
            'last_update_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 52.2297)
            ->assertJsonPath('data.last_known_position.longitude', 21.0122);

        $position = $response->json('data.last_known_position');
        $this->assertIsFloat($position['latitude']);
        $this->assertIsFloat($position['longitude']);
    }

    public function test_invalid_priority_one_longitude_falls_through_to_priority_two(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $active = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($active, $shipment, [
            'latitude' => 52.2297000,
            'longitude' => 181.0000000,
            'last_update_at' => now(),
        ]);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => 50.0614000,
            'longitude' => 19.9383000,
            'last_update_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 50.0614)
            ->assertJsonPath('data.last_known_position.longitude', 19.9383);
    }

    public function test_invalid_priority_two_falls_through_to_tracking_log(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => -91.0000000,
            'longitude' => 10.0000000,
            'last_update_at' => now(),
        ]);

        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 53.4285000,
            'longitude' => 14.5528000,
            'recorded_at' => now()->subMinutes(3),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 53.4285)
            ->assertJsonPath('data.last_known_position.longitude', 14.5528);

        $position = $response->json('data.last_known_position');
        $this->assertIsFloat($position['latitude']);
        $this->assertIsFloat($position['longitude']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $position['recorded_at']);
    }

    public function test_invalid_priority_three_returns_null(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.2297000,
            'longitude' => -181.0000000,
            'recorded_at' => now()->subMinute(),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.last_known_position', null);
    }

    public function test_priority_one_older_active_state_wins_over_newer_historical_state(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $historical = $this->makeInactiveAssignment($shipment);
        $this->makeTrackingState($historical, $shipment, [
            'latitude' => 51.7592000,
            'longitude' => 19.4560000,
            'last_update_at' => now(),
        ]);

        $active = $this->makeActiveAssignment($shipment);
        $this->makeTrackingState($active, $shipment, [
            'latitude' => 52.2297000,
            'longitude' => 21.0122000,
            'last_update_at' => now()->subHours(3),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.last_known_position.latitude', 52.2297)
            ->assertJsonPath('data.last_known_position.longitude', 21.0122);
    }

    public function test_tracking_log_fallback_serializes_coordinates_as_floats(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 52.4064000,
            'longitude' => 16.9252000,
            'recorded_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking');

        $response->assertOk();
        $position = $response->json('data.last_known_position');
        $this->assertSame(['latitude', 'longitude', 'recorded_at'], array_keys($position));
        $this->assertIsFloat($position['latitude']);
        $this->assertIsFloat($position['longitude']);
        $this->assertIsString($position['recorded_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $position['recorded_at']);
        $this->assertForbiddenTelemetryAbsent($response->json('data'));
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeShipment(Company $company, array $overrides = []): Shipment
    {
        return Shipment::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusInTransit->id,
            'priority' => 'normal',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'pickup_company' => 'Pickup Co',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'delivery_company' => 'Delivery Co',
            'planned_pickup_date' => now()->addDay()->toDateString(),
            'planned_delivery_date' => now()->addDays(3)->toDateString(),
        ], $overrides));
    }

    private function makeActiveAssignment(Shipment $shipment): Assignment
    {
        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => null,
            'status_id' => $this->assignmentStatus->id,
            'is_active' => true,
            'assigned_by' => User::factory()->create(['role_id' => $this->adminRole->id])->id,
        ]);
    }

    private function makeInactiveAssignment(Shipment $shipment): Assignment
    {
        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => null,
            'status_id' => $this->assignmentStatus->id,
            'is_active' => false,
            'ended_at' => now()->subDay(),
            'assigned_by' => User::factory()->create(['role_id' => $this->adminRole->id])->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTrackingState(Assignment $assignment, Shipment $shipment, array $overrides = []): TrackingState
    {
        return TrackingState::query()->create(array_merge([
            'assignment_id' => $assignment->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => $assignment->vehicle_id,
            'driver_id' => $assignment->driver_id,
            'trailer_id' => null,
            'latitude' => 52.2297000,
            'longitude' => 21.0122000,
            'speed' => 0,
            'heading' => 0,
            'accuracy' => 5,
            'movement_status' => 'Idle',
            'online_status' => 'Online',
            'signal_quality' => 'Good',
            'last_update_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertForbiddenTelemetryAbsent(array $data): void
    {
        foreach ([
            'driver_id',
            'vehicle_id',
            'assignment_id',
            'trailer_id',
            'speed',
            'heading',
            'accuracy',
            'movement_status',
            'online_status',
            'signal_quality',
            'tracking_logs',
            'tracking_states',
            'eta',
            'fleet',
            'engine',
            'engine_ops',
            'plate',
            'active_assignment',
            'provider',
            'encoded',
            'legs',
            'steps',
            'waypoints',
            'duration',
            'distance',
            'deviation',
            'deviation_distance',
            'route_status',
            'route_geometry',
            'api_payload',
            'geometry',
            'dispatcher_notes',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $data, $forbidden);
        }

        $this->assertArrayHasKey('route', $data);
        if ($data['route'] !== null) {
            $this->assertValidClientRoute($data['route']);
        }

        if (isset($data['last_known_position']) && is_array($data['last_known_position'])) {
            foreach ([
                'driver_id',
                'vehicle_id',
                'assignment_id',
                'trailer_id',
                'speed',
                'heading',
                'accuracy',
                'movement_status',
                'online_status',
                'signal_quality',
                'last_update_at',
            ] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $data['last_known_position'], $forbidden);
            }
        }
    }

    /**
     * Final C5.5-C2 allowlist — customer-safe projection keys only.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertClientTrackingAllowlist(array $data): void
    {
        $allowed = [
            'shipment_id',
            'shipment_number',
            'status',
            'planned_pickup_date',
            'planned_delivery_date',
            'actual_pickup_date',
            'actual_delivery_date',
            'origin',
            'destination',
            'customer_company',
            'created_at',
            'milestones',
            'last_known_position',
            'location_freshness',
            'route',
        ];

        $this->assertSame($allowed, array_keys($data));

        foreach ($allowed as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    /**
     * @param  array{latitude: mixed, longitude: mixed}|null  $coordinates
     */
    private function assertValidEndpointCoordinates(?array $coordinates): void
    {
        if ($coordinates === null) {
            return;
        }

        $this->assertSame(['latitude', 'longitude'], array_keys($coordinates));
        $this->assertIsNumeric($coordinates['latitude']);
        $this->assertIsNumeric($coordinates['longitude']);

        $lat = (float) $coordinates['latitude'];
        $lng = (float) $coordinates['longitude'];

        $this->assertTrue(is_finite($lat));
        $this->assertTrue(is_finite($lng));
        $this->assertGreaterThanOrEqual(-90.0, $lat);
        $this->assertLessThanOrEqual(90.0, $lat);
        $this->assertGreaterThanOrEqual(-180.0, $lng);
        $this->assertLessThanOrEqual(180.0, $lng);
    }

    /**
     * @param  array{latitude: mixed, longitude: mixed, recorded_at: mixed}|null  $position
     */
    private function assertValidLastKnownPosition(?array $position): void
    {
        $this->assertNotNull($position);
        $this->assertSame(['latitude', 'longitude', 'recorded_at'], array_keys($position));
        $this->assertIsNumeric($position['latitude']);
        $this->assertIsNumeric($position['longitude']);
        $this->assertIsString($position['recorded_at']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $position['recorded_at']
        );

        $lat = (float) $position['latitude'];
        $lng = (float) $position['longitude'];
        $this->assertTrue(is_finite($lat));
        $this->assertTrue(is_finite($lng));
        $this->assertGreaterThanOrEqual(-90.0, $lat);
        $this->assertLessThanOrEqual(90.0, $lat);
        $this->assertGreaterThanOrEqual(-180.0, $lng);
        $this->assertLessThanOrEqual(180.0, $lng);
    }

    /**
     * @param  array{state: mixed, recorded_at: mixed, age_seconds: mixed}  $freshness
     * @param  array{recorded_at?: mixed}|null  $lastKnownPosition
     */
    private function assertValidLocationFreshness(array $freshness, ?array $lastKnownPosition): void
    {
        $this->assertSame(['state', 'recorded_at', 'age_seconds'], array_keys($freshness));
        $this->assertContains($freshness['state'], ['unavailable', 'fresh', 'stale', 'very_stale']);
        $this->assertIsInt($freshness['age_seconds']);
        $this->assertGreaterThanOrEqual(0, $freshness['age_seconds']);

        if ($freshness['state'] === 'unavailable') {
            $this->assertNull($freshness['recorded_at']);
            $this->assertSame(0, $freshness['age_seconds']);

            return;
        }

        $this->assertIsString($freshness['recorded_at']);
        $this->assertNotNull($lastKnownPosition);
        $this->assertSame($lastKnownPosition['recorded_at'], $freshness['recorded_at']);
    }

    /**
     * @param  array{coordinates: list<array{latitude: mixed, longitude: mixed}>}|null  $route
     */
    private function assertValidClientRoute(?array $route): void
    {
        $this->assertNotNull($route);
        $this->assertSame(['coordinates'], array_keys($route));
        $this->assertIsArray($route['coordinates']);
        $this->assertGreaterThanOrEqual(2, count($route['coordinates']));

        foreach ($route['coordinates'] as $point) {
            $this->assertValidEndpointCoordinates($point);
        }
    }

    private function assertForbiddenOpsArtifactsAbsent(string $body): void
    {
        foreach ([
            'graphhopper',
            'GEOMETRY_ENCODED_SECRET',
            'internal-route-uuid',
            '2099-12-31',
            'engine_ops',
            'OFF_ROUTE',
            'deviation_distance',
            'turn right',
            'secret-hub',
            '"provider"',
            '"encoded"',
            '"legs"',
            '"steps"',
            '"waypoints"',
            '"duration"',
            '"distance"',
            '"eta"',
            'live-fleet',
            'simulation',
            'execution_time_ms',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, $forbidden);
        }
    }
}
