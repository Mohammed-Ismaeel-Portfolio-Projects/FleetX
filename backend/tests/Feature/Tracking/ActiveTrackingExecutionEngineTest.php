<?php

namespace Tests\Feature\Tracking;

use App\Events\Tracking\TrackingStateUpdated;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\TrackingSession;
use App\Models\TrackingState;
use App\Models\Vehicle;
use App\Models\User;
use App\Services\Tracking\ShipmentTrackingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ActiveTrackingExecutionEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private ShipmentOrder $shipmentOrder;
    private Shipment $shipment;
    private Assignment $assignment;
    private Driver $driver;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\ShipmentOrderStatusesSeeder::class);

        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $company = Company::factory()->create();

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $company->id,
        ]);

        $this->driver = Driver::factory()->create(['first_name' => 'Ahmad', 'last_name' => 'Ali']);
        $this->vehicle = Vehicle::factory()->create(['plate_number' => 'FLEETX-TRK-01']);
        $this->shipment = Shipment::factory()->create();

        $this->assignment = Assignment::factory()->create([
            'shipment_id' => $this->shipment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'is_active' => true,
        ]);

        $this->shipmentOrder = ShipmentOrder::factory()->create([
            'converted_shipment_id' => $this->shipment->id,
            'planned_route_geometry' => [
                [33.5138, 36.2765],
                [33.5200, 36.2800],
                [33.5300, 36.2900],
            ],
        ]);
    }

    public function test_starting_session_triggers_immediate_telemetry_tick_and_dispatches_event(): void
    {
        Event::fake([TrackingStateUpdated::class]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson('/api/v1/dispatcher/tracking-sessions/start', [
                'shipment_order_id' => $this->shipmentOrder->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE');

        $session = TrackingSession::where('shipment_order_id', $this->shipmentOrder->id)->firstOrFail();
        $this->assertEquals('ACTIVE', $session->status);

        Event::assertDispatched(TrackingStateUpdated::class, function ($event) {
            return $event->vehicleId === (string) $this->vehicle->id &&
                $event->shipmentId === (string) $this->shipment->id;
        });
    }

    public function test_active_session_generates_telemetry_and_advances_coordinates(): void
    {
        $session = TrackingSession::create([
            'shipment_order_id' => $this->shipmentOrder->id,
            'shipment_id' => $this->shipment->id,
            'assignment_id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);

        $state1 = $engine->tick($session);
        $this->assertNotNull($state1);
        $this->assertEquals(33.5141, round((float) $state1->latitude, 4));
        $this->assertEquals(36.2767, round((float) $state1->longitude, 4));

        $state2 = $engine->tick($session);
        $this->assertNotNull($state2);
        $this->assertGreaterThan((float) $state1->latitude, (float) $state2->latitude);
        $this->assertLessThanOrEqual(33.5200, (float) $state2->latitude);
    }

    public function test_paused_terminated_and_completed_sessions_do_not_generate_telemetry(): void
    {
        $session = TrackingSession::create([
            'shipment_order_id' => $this->shipmentOrder->id,
            'shipment_id' => $this->shipment->id,
            'assignment_id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'PAUSED',
        ]);

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);

        $this->assertNull($engine->tick($session));

        $session->status = 'TERMINATED';
        $session->save();
        $this->assertNull($engine->tick($session));

        $session->status = 'COMPLETED';
        $session->save();
        $this->assertNull($engine->tick($session));
    }

    public function test_simulation_overrides_modify_telemetry(): void
    {
        $session = TrackingSession::create([
            'shipment_order_id' => $this->shipmentOrder->id,
            'shipment_id' => $this->shipment->id,
            'assignment_id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'ACTIVE',
            'forced_speed_kmh' => 120.0,
            'forced_route_deviation' => true,
        ]);

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);

        $state = $engine->tick($session);
        $this->assertNotNull($state);
        $this->assertEquals(120.0, $state->speed);
        $this->assertGreaterThan(33.5280, (float) $state->latitude);

        // Sudden stop override
        $session->forced_deceleration = true;
        $session->save();

        $state2 = $engine->tick($session);
        $this->assertEquals(0.0, $state2->speed);
    }

    public function test_route_completion_transitions_session_to_completed(): void
    {
        $session = TrackingSession::create([
            'shipment_order_id' => $this->shipmentOrder->id,
            'shipment_id' => $this->shipment->id,
            'assignment_id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'ACTIVE',
            'forced_speed_kmh' => 5000.0, // High speed to complete short test route in 1 tick
        ]);

        /** @var ShipmentTrackingEngine $engine */
        $engine = app(ShipmentTrackingEngine::class);

        $engine->tick($session);

        $session->refresh();
        $this->assertEquals('COMPLETED', $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_artisan_command_ticks_active_sessions(): void
    {
        $session = TrackingSession::create([
            'shipment_order_id' => $this->shipmentOrder->id,
            'shipment_id' => $this->shipment->id,
            'assignment_id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'ACTIVE',
        ]);

        $this->artisan('tracking:tick-sessions --duration=0')
            ->assertExitCode(0);

        $state = TrackingState::where('shipment_id', $this->shipment->id)->first();
        $this->assertNotNull($state);
        $this->assertEquals(33.5141, round((float) $state->latitude, 4));
    }
}
