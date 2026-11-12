<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D4 — Pickup / delivery execution phases (verification gates only; no QR).
 */
class DriverExecutionPhaseTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $cancelledStatus;

    private Status $assignedShipmentStatus;

    private Status $inTransitShipmentStatus;

    private Status $deliveredShipmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->assignedShipmentStatus = $this->shipmentStatus('Assigned');
        $this->inTransitShipmentStatus = $this->shipmentStatus('In Transit');
        $this->deliveredShipmentStatus = $this->shipmentStatus('Delivered');
    }

    public function test_own_driver_can_arrive_at_pickup(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-arrive-own@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_ARRIVED)
            ->assertJsonPath('data.execution.stage', 'pickup')
            ->assertJsonPath('data.execution.verification_boundary', false);

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'execution_phase' => DriverExecutionPhase::PICKUP_ARRIVED,
        ]);
        $this->assertSame(
            $this->assignedShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_foreign_driver_cannot_arrive(): void
    {
        [$userA] = $this->makeLinkedDriver('d4-arrive-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d4-arrive-b@fleetx.test');
        $assignmentB = $this->makeInOperation($driverB, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/pickup/arrive')
            ->assertNotFound();
    }

    public function test_unlinked_driver_denied_arrive(): void
    {
        $user = User::factory()->create([
            'email' => 'd4-arrive-unlink@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/pickup/arrive')
            ->assertForbidden();
    }

    public function test_unauthenticated_denied_arrive(): void
    {
        $this->postJson('/api/v1/driver/assignments/1/pickup/arrive')->assertUnauthorized();
    }

    public function test_client_denied_arrive(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd4-arrive-client@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/pickup/arrive')
            ->assertForbidden();
    }

    public function test_arrive_from_invalid_phase_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-arrive-bad@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_LOADING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertStatus(409);

        $this->assertSame(DriverExecutionPhase::PICKUP_LOADING, $assignment->fresh()->execution_phase);
    }

    public function test_duplicate_arrive_is_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-arrive-idem@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_ARRIVED);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_ARRIVED);
    }

    public function test_start_loading_only_after_arrival(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-load-own@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_ARRIVED);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_LOADING);
    }

    public function test_loading_from_invalid_phase_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-load-bad@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading')
            ->assertStatus(409);
    }

    public function test_duplicate_loading_is_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-load-idem@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_LOADING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_LOADING);
    }

    public function test_loading_complete_reaches_verification_boundary(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-load-done@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_LOADING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading-complete')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_VERIFYING)
            ->assertJsonPath('data.execution.verification_boundary', true);

        $this->assertSame(
            $this->assignedShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_cannot_bypass_pickup_verification(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-bypass-pu@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $token = $this->loginToken($user->email);

        // Cannot jump into delivery without D5 pickup verification
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/arrive')
            ->assertStatus(409);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertStatus(409);

        // Duplicate loading-complete at boundary is idempotent (not a bypass)
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading-complete')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_VERIFYING);

        $this->assertSame(DriverExecutionPhase::PICKUP_VERIFYING, $assignment->fresh()->execution_phase);
        $this->assertSame(
            $this->assignedShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_delivery_arrival_only_from_en_route(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-del-arrive@fleetx.test');
        $assignment = $this->makeInOperation(
            $driver,
            DriverExecutionPhase::DELIVERY_EN_ROUTE,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/arrive')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::DELIVERY_ARRIVED)
            ->assertJsonPath('data.execution.stage', 'delivery');
    }

    public function test_foreign_driver_denied_delivery_arrive(): void
    {
        [$userA] = $this->makeLinkedDriver('d4-del-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d4-del-b@fleetx.test');
        $assignmentB = $this->makeInOperation(
            $driverB,
            DriverExecutionPhase::DELIVERY_EN_ROUTE,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/delivery/arrive')
            ->assertNotFound();
    }

    public function test_delivery_unloading_only_after_arrival(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-unload@fleetx.test');
        $assignment = $this->makeInOperation(
            $driver,
            DriverExecutionPhase::DELIVERY_ARRIVED,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/unloading')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::DELIVERY_UNLOADING);
    }

    public function test_unloading_complete_reaches_verification_boundary(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-unload-done@fleetx.test');
        $assignment = $this->makeInOperation(
            $driver,
            DriverExecutionPhase::DELIVERY_UNLOADING,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/unloading-complete')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::DELIVERY_VERIFYING)
            ->assertJsonPath('data.execution.verification_boundary', true);

        $this->assertSame(
            $this->inTransitShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_cannot_mark_delivered_from_unloading(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-no-delivered@fleetx.test');
        $assignment = $this->makeInOperation(
            $driver,
            DriverExecutionPhase::DELIVERY_UNLOADING,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/unloading-complete')
            ->assertOk();

        $fresh = $assignment->fresh()->load('shipment');
        $this->assertSame(DriverExecutionPhase::DELIVERY_VERIFYING, $fresh->execution_phase);
        $this->assertSame($this->inTransitShipmentStatus->id, $fresh->shipment->status_id);
        $this->assertNotSame($this->deliveredShipmentStatus->id, $fresh->shipment->status_id);
    }

    public function test_cannot_bypass_delivery_verification(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-bypass-del@fleetx.test');
        $assignment = $this->makeInOperation(
            $driver,
            DriverExecutionPhase::DELIVERY_VERIFYING,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/arrive')
            ->assertStatus(409);

        $this->assertSame(DriverExecutionPhase::DELIVERY_VERIFYING, $assignment->fresh()->execution_phase);
        $this->assertSame(
            $this->inTransitShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_concurrent_transition_protection(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-concurrent@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $results = [];
        DB::transaction(function () use ($assignment, $token, &$results) {
            Assignment::query()->whereKey($assignment->id)->lockForUpdate()->first();
            $results[] = $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
                ->status();
        });

        $this->assertContains($results[0], [200]);
        $this->assertSame(DriverExecutionPhase::PICKUP_ARRIVED, $assignment->fresh()->execution_phase);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_ARRIVED);
    }

    public function test_stale_inactive_assignment_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-stale@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $assignment->update(['is_active' => false]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertStatus(409);
    }

    public function test_cancelled_assignment_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->cancelledStatus,
            false,
            DriverExecutionPhase::PICKUP_PENDING
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertStatus(409);
    }

    public function test_reassigned_assignment_rejected(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d4-reas-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d4-reas-b@fleetx.test');
        $assignment = $this->makeInOperation($driverA, DriverExecutionPhase::PICKUP_PENDING);
        $assignment->update([
            'driver_id' => $driverB->id,
            'is_active' => true,
        ]);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')
            ->assertNotFound();
    }

    public function test_invalid_phase_jump_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-jump@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/unloading')
            ->assertStatus(409);

        $this->assertSame(DriverExecutionPhase::PICKUP_PENDING, $assignment->fresh()->execution_phase);
    }

    public function test_client_supplied_driver_id_cannot_override_ownership(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-probe-drv@fleetx.test');
        [, $other] = $this->makeLinkedDriver('d4-probe-other@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive', [
                'driver_id' => $other->id,
            ])
            ->assertStatus(422);
    }

    public function test_client_supplied_shipment_id_cannot_override_binding(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-probe-shp@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive', [
                'shipment_id' => '00000000-0000-0000-0000-000000000099',
                'execution_phase' => DriverExecutionPhase::DELIVERY_ARRIVED,
            ])
            ->assertStatus(422);
    }

    public function test_start_sets_pickup_pending_phase(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-start-phase@fleetx.test');
        $ready = $this->assignmentStatus('Ready');
        $assignment = $this->makeAssignmentFor($driver, $ready, true, null);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_PENDING);
    }

    public function test_pickup_flow_does_not_advance_shipment_to_in_transit(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d4-no-transit@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);
        $auth = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')->assertOk();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading')->assertOk();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading-complete')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_VERIFYING);

        $this->assertSame(
            $this->assignedShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
    }

    private function shipmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'shipment'],
            ['name' => $name, 'type' => 'shipment']
        );
    }

    /**
     * @return array{0: User, 1: Driver}
     */
    private function makeLinkedDriver(string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $driver = Driver::factory()->create(['user_id' => $user->id]);

        return [$user, $driver];
    }

    private function makeInOperation(
        Driver $driver,
        string $phase,
        ?Status $shipmentStatus = null
    ): Assignment {
        return $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            $phase,
            $shipmentStatus ?? $this->assignedShipmentStatus
        );
    }

    private function makeAssignmentFor(
        Driver $driver,
        Status $status,
        bool $isActive,
        ?string $executionPhase = null,
        ?Status $shipmentStatus = null
    ): Assignment {
        $shipment = Shipment::factory()->create([
            'status_id' => ($shipmentStatus ?? $this->assignedShipmentStatus)->id,
        ]);
        $vehicle = Vehicle::factory()->create();
        $trailer = Trailer::factory()->create();
        $dispatcher = User::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $status->id,
            'is_active' => $isActive,
            'execution_phase' => $executionPhase,
        ]);
    }

    private function loginToken(string $email): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ]);
        $login->assertOk();

        return $login->json('data.access_token');
    }
}
