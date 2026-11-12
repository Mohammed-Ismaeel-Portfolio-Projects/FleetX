<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D9 — Driver IDOR and role denial matrix (supplemental security hardening).
 */
class DriverIDORSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Status $assignedStatus;

    private Status $inOperationStatus;

  protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->assignedStatus = $this->assignmentStatus('Assigned');
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->shipmentStatus(ShipmentStatus::IN_TRANSIT);
    }

    public function test_admin_forbidden_on_driver_work_active(): void
    {
        $admin = User::factory()->create([
            'email' => 'd9-admin@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->adminRole->id,
        ]);
        $token = $this->loginToken($admin->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertForbidden();
    }

    public function test_dispatcher_forbidden_on_driver_work_active(): void
    {
        $dispatcher = User::factory()->create([
            'email' => 'd9-disp@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->dispatcherRole->id,
        ]);
        $token = $this->loginToken($dispatcher->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertForbidden();
    }

    public function test_other_driver_cannot_fail_delivery(): void
    {
        [, $owner] = $this->makeLinkedDriver('d9-fail-own@fleetx.test');
        [$otherUser] = $this->makeLinkedDriver('d9-fail-other@fleetx.test');
        $assignment = $this->makeInOperation($owner, DriverExecutionPhase::DELIVERY_EN_ROUTE);

        $token = $this->loginToken($otherUser->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/fail-delivery', [
                'type' => 'delivery_problem',
            ])
            ->assertNotFound();
    }

    public function test_fail_delivery_idempotent_when_already_failed(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d9-fail-idem@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::DELIVERY_FAILED);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/fail-delivery', [
                'type' => 'delivery_problem',
            ])
            ->assertOk()
            ->assertJsonPath('data.failed', true);
    }

    public function test_mark_all_read_does_not_affect_other_driver(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d9-read-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d9-read-b@fleetx.test');

        DriverNotification::query()->create([
            'driver_id' => $driverA->id,
            'code' => 'assignment.assigned',
            'title' => 'A',
            'body' => 'A',
            'severity' => 'info',
        ]);
        $foreign = DriverNotification::query()->create([
            'driver_id' => $driverB->id,
            'code' => 'assignment.assigned',
            'title' => 'B',
            'body' => 'B',
            'severity' => 'info',
        ]);

        $token = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/read-all')
            ->assertOk();

        $this->assertNull(DriverNotification::query()->find($foreign->id)?->read_at);
    }

    public function test_soft_deleted_assignment_returns_404_for_driver_show(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d9-soft@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, true);
        $assignment->delete();

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id)
            ->assertNotFound();
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

        return [$user, Driver::factory()->create(['user_id' => $user->id])];
    }

    private function makeAssignmentFor(Driver $driver, bool $isActive): Assignment
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
            'status_id' => $this->assignedStatus->id,
            'is_active' => $isActive,
        ]);
    }

    private function makeInOperation(Driver $driver, string $phase): Assignment
    {
        $shipment = Shipment::factory()->create([
            'status_id' => $this->shipmentStatus(ShipmentStatus::IN_TRANSIT)->id,
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
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
            'execution_phase' => $phase,
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
