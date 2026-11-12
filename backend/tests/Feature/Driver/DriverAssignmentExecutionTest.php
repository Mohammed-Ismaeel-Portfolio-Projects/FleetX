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
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D3 — Driver acknowledge + start operation mutations.
 */
class DriverAssignmentExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $assignedStatus;

    private Status $readyStatus;

    private Status $inOperationStatus;

    private Status $completedStatus;

    private Status $cancelledStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->assignedStatus = $this->assignmentStatus('Assigned');
        $this->readyStatus = $this->assignmentStatus('Ready');
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->completedStatus = $this->assignmentStatus('Completed');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
    }

    public function test_own_driver_can_acknowledge_assigned(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-ack-own@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->assignedStatus, true);
        $token = $this->loginToken($user->email);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge');

        $response->assertOk()
            ->assertJsonPath('data.id', $assignment->id)
            ->assertJsonPath('data.status.name', 'Ready')
            ->assertJsonPath('data.status.key', 'ready')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'status_id' => $this->readyStatus->id,
            'updated_by' => $user->id,
        ]);
    }

    public function test_duplicate_acknowledge_is_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-ack-idem@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->readyStatus, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'Ready');
    }

    public function test_other_driver_cannot_acknowledge(): void
    {
        [$userA] = $this->makeLinkedDriver('d3-ack-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d3-ack-b@fleetx.test');
        $assignmentB = $this->makeAssignmentFor($driverB, $this->assignedStatus, true);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/acknowledge')
            ->assertNotFound();
    }

    public function test_unlinked_driver_cannot_acknowledge(): void
    {
        $user = User::factory()->create([
            'email' => 'd3-ack-unlink@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/acknowledge')
            ->assertForbidden();
    }

    public function test_client_cannot_acknowledge(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd3-ack-client@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/acknowledge')
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_acknowledge(): void
    {
        $this->postJson('/api/v1/driver/assignments/1/acknowledge')->assertUnauthorized();
    }

    public function test_cancelled_assignment_cannot_be_acknowledged(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-ack-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->cancelledStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge')
            ->assertStatus(409);
    }

    public function test_completed_assignment_cannot_be_acknowledged(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-ack-complete@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->completedStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge')
            ->assertStatus(409);
    }

    public function test_own_driver_can_start_after_acknowledge(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-start-own@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->readyStatus, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'In Operation')
            ->assertJsonPath('data.status.key', 'in_operation');

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'status_id' => $this->inOperationStatus->id,
            'execution_phase' => 'pickup_pending',
            'updated_by' => $user->id,
        ]);
    }

    public function test_driver_cannot_start_before_acknowledgement(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-start-early@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->assignedStatus, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertStatus(409);
    }

    public function test_other_driver_cannot_start(): void
    {
        [$userA] = $this->makeLinkedDriver('d3-start-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d3-start-b@fleetx.test');
        $assignmentB = $this->makeAssignmentFor($driverB, $this->readyStatus, true);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/start')
            ->assertNotFound();
    }

    public function test_unlinked_driver_cannot_start(): void
    {
        $user = User::factory()->create([
            'email' => 'd3-start-unlink@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/start')
            ->assertForbidden();
    }

    public function test_client_cannot_start(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd3-start-client@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/start')
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_start(): void
    {
        $this->postJson('/api/v1/driver/assignments/1/start')->assertUnauthorized();
    }

    public function test_cancelled_assignment_cannot_start(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-start-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->cancelledStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertStatus(409);
    }

    public function test_completed_assignment_cannot_start(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-start-complete@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->completedStatus, false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertStatus(409);
    }

    public function test_duplicate_start_is_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-start-idem@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->inOperationStatus, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.status.name', 'In Operation');
    }

    public function test_ownership_override_params_prohibited_on_acknowledge(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-ack-probe@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->assignedStatus, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge', [
                'driver_id' => 999,
                'status_id' => $this->completedStatus->id,
            ])
            ->assertStatus(422);
    }

    public function test_full_ack_then_start_flow(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d3-flow@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, $this->assignedStatus, true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.status.key', 'ready');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.status.key', 'in_operation')
            ->assertJsonPath('data.execution.phase', 'pickup_pending');
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
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

    private function makeAssignmentFor(Driver $driver, Status $status, bool $isActive): Assignment
    {
        $shipment = Shipment::factory()->create();
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
