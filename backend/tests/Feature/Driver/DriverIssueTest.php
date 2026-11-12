<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Issue;
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
 * D7 — Driver issues + fail-delivery.
 */
class DriverIssueTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $cancelledStatus;

    private Status $inTransitShipmentStatus;

    private Status $deliveredShipmentStatus;

    private Status $openIssueStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->inTransitShipmentStatus = $this->shipmentStatus(ShipmentStatus::IN_TRANSIT);
        $this->deliveredShipmentStatus = $this->shipmentStatus(ShipmentStatus::DELIVERED);
        $this->openIssueStatus = Status::firstOrCreate(
            ['name' => 'Open', 'type' => 'issue'],
            ['name' => 'Open', 'type' => 'issue']
        );
        Status::firstOrCreate(['name' => 'In Progress', 'type' => 'issue']);
        Status::firstOrCreate(['name' => 'Resolved', 'type' => 'issue']);
    }

    public function test_own_driver_can_create_issue(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-iss-own@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::DELIVERY_EN_ROUTE);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', [
                'type' => 'traffic',
            ])
            ->assertCreated()
            ->assertJsonPath('data.issue.type', 'traffic')
            ->assertJsonPath('data.issue.status.name', 'Open');

        $issue = Issue::first();
        $this->assertNotNull($issue);
        $this->assertSame($driver->id, $issue->driver_id);
        $this->assertSame($assignment->shipment_id, $issue->shipment_id);
        $this->assertSame($this->openIssueStatus->id, $issue->status_id);
    }

    public function test_other_requires_description(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-iss-other@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::DELIVERY_ARRIVED);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', [
                'type' => 'other',
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', [
                'type' => 'other',
                'description' => 'Customer closed early',
            ])
            ->assertCreated();
    }

    public function test_invalid_reason_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-iss-bad@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', [
                'type' => 'not_a_reason',
            ])
            ->assertStatus(422);
    }

    public function test_other_driver_denied(): void
    {
        [, $owner] = $this->makeLinkedDriver('d7-iss-owner@fleetx.test');
        [$other] = $this->makeLinkedDriver('d7-iss-intruder@fleetx.test');
        $assignment = $this->makeInOperation($owner, DriverExecutionPhase::DELIVERY_EN_ROUTE);
        $token = $this->loginToken($other->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', [
                'type' => 'accident',
            ])
            ->assertNotFound();
    }

    public function test_driver_cannot_change_issue_status(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-iss-status@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::DELIVERY_EN_ROUTE);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', [
                'type' => 'vehicle_problem',
                'status_id' => Status::where('name', 'Resolved')->where('type', 'issue')->first()->id,
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/issues', [
                'shipment_id' => $assignment->shipment_id,
                'driver_id' => $driver->id,
                'type' => 'traffic',
                'description' => 'x',
                'status_id' => $this->openIssueStatus->id,
            ])
            ->assertForbidden();
    }

    public function test_fail_delivery_records_issue_without_delivered(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-fail-own@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::DELIVERY_ARRIVED);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/fail-delivery', [
                'type' => 'delivery_problem',
                'description' => 'Access code wrong',
            ])
            ->assertOk()
            ->assertJsonPath('data.failed', true)
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::DELIVERY_FAILED)
            ->assertJsonPath('data.assignment.shipment.status.name', ShipmentStatus::IN_TRANSIT);

        $this->assertSame(1, Issue::count());
        $this->assertTrue($assignment->fresh()->is_active);
        $this->assertSame($this->inOperationStatus->id, $assignment->fresh()->status_id);
    }

    public function test_fail_delivery_denied_after_verified(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-fail-late@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::DELIVERY_VERIFIED,
            $this->deliveredShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/fail-delivery', [
                'type' => 'delivery_problem',
            ])
            ->assertStatus(409);

        $this->assertSame(ShipmentStatus::DELIVERED, $assignment->fresh()->shipment->status->name);
    }

    public function test_cancelled_assignment_cannot_report_issue(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d7-iss-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->cancelledStatus,
            false,
            DriverExecutionPhase::DELIVERY_EN_ROUTE,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', [
                'type' => 'traffic',
            ])
            ->assertStatus(409);
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

    private function makeInOperation(Driver $driver, string $phase): Assignment
    {
        return $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            $phase,
            $this->inTransitShipmentStatus
        );
    }

    private function makeAssignmentFor(
        Driver $driver,
        Status $status,
        bool $isActive,
        ?string $phase,
        Status $shipmentStatus
    ): Assignment {
        $shipment = Shipment::factory()->create(['status_id' => $shipmentStatus->id]);
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
