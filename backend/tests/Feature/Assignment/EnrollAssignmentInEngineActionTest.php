<?php

namespace Tests\Feature\Assignment;

use App\Actions\Assignment\EnrollAssignmentInEngineAction;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 3 — Engine trip enrollment.
 */
class EnrollAssignmentInEngineActionTest extends TestCase
{
    use RefreshDatabase;

    private EnrollAssignmentInEngineAction $action;

    private Status $assignmentStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
        $this->action = app(EnrollAssignmentInEngineAction::class);
        $this->assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
    }

    public function test_valid_assigned_assignment_can_be_enrolled(): void
    {
        $assignment = $this->makeEligibleAssignment();

        $result = $this->action->execute($assignment->id);

        $this->assertTrue($result->success);
        $assignment->refresh();
        $this->assertSame(EngineEnrollmentStatus::ENROLLED, $assignment->engine_enrollment_status);
        $this->assertNotNull($assignment->engine_enrolled_at);
        $this->assertSame(1, SimulationEligibleAssignments::query()->count());
    }

    public function test_enrollment_is_idempotent(): void
    {
        $assignment = $this->makeEligibleAssignment();

        $first = $this->action->execute($assignment->id);
        $second = $this->action->execute($assignment->id);
        $third = $this->action->execute($assignment->id);

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertTrue($third->success);
        $this->assertSame(1, Assignment::query()->whereNotNull('engine_enrolled_at')->count());
        $this->assertSame(1, SimulationEligibleAssignments::query()->count());
        $this->assertStringContainsString('already enrolled', $second->message);
    }

    public function test_waiting_assignment_shipment_cannot_enroll(): void
    {
        $assignment = $this->makeEligibleAssignment(ShipmentStatus::WAITING_ASSIGNMENT);

        $result = $this->action->execute($assignment->id);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertNull($assignment->fresh()->engine_enrollment_status);
        $this->assertSame(0, SimulationEligibleAssignments::query()->count());
    }

    public function test_inactive_assignment_cannot_enroll(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $assignment->update(['is_active' => false]);

        $result = $this->action->execute($assignment->id);

        $this->assertFalse($result->success);
        $this->assertSame(0, SimulationEligibleAssignments::query()->count());
    }

    public function test_missing_vehicle_rejected(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $assignment->update(['vehicle_id' => null]);

        $result = $this->action->execute($assignment->id);

        $this->assertFalse($result->success);
        $this->assertSame(0, SimulationEligibleAssignments::query()->count());
    }

    public function test_missing_driver_rejected(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $driver = Driver::query()->findOrFail($assignment->driver_id);
        $driver->delete();

        $result = $this->action->execute($assignment->id);

        $this->assertFalse($result->success);
        $this->assertSame(0, SimulationEligibleAssignments::query()->count());
    }

    public function test_enrollment_does_not_advance_shipment_status_or_history(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $beforeStatus = $assignment->shipment->status_id;
        $beforeHistory = ShipmentStatusHistory::query()->count();

        $this->action->execute($assignment->id);

        $this->assertSame($beforeStatus, $assignment->shipment->fresh()->status_id);
        $this->assertSame(ShipmentStatus::ASSIGNED, $assignment->shipment->fresh()->status->name);
        $this->assertSame($beforeHistory, ShipmentStatusHistory::query()->count());
    }

    public function test_non_enrolled_active_assignment_not_simulation_eligible(): void
    {
        $this->makeEligibleAssignment();

        $this->assertSame(1, Assignment::query()->active()->count());
        $this->assertSame(0, SimulationEligibleAssignments::query()->count());
    }

    public function test_enrolled_assignment_is_simulation_eligible(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $this->action->execute($assignment->id);

        $this->assertTrue(
            SimulationEligibleAssignments::query()->whereKey($assignment->id)->exists()
        );
    }

    public function test_completed_enrollment_cannot_re_enroll(): void
    {
        $assignment = $this->makeEligibleAssignment();
        $assignment->update([
            'engine_enrollment_status' => EngineEnrollmentStatus::COMPLETED,
            'engine_enrolled_at' => now()->subHour(),
        ]);

        $result = $this->action->execute($assignment->id);

        $this->assertFalse($result->success);
        $this->assertSame(0, SimulationEligibleAssignments::query()->count());
    }

    public function test_dispatcher_can_enroll_via_api(): void
    {
        $dispatcherRole = \App\Models\Role::where('name', 'dispatcher')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
        $assignment = $this->makeEligibleAssignment();

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/assignments/'.$assignment->id.'/engine-enroll')
            ->assertOk()
            ->assertJsonPath('data.engine_enrollment_status', EngineEnrollmentStatus::ENROLLED);

        $this->assertSame(1, SimulationEligibleAssignments::query()->count());
    }

    public function test_idempotent_api_enroll_returns_existing(): void
    {
        $dispatcherRole = \App\Models\Role::where('name', 'dispatcher')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
        $assignment = $this->makeEligibleAssignment();
        $this->action->execute($assignment->id);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/assignments/'.$assignment->id.'/engine-enroll')
            ->assertOk()
            ->assertJsonPath('message', 'Assignment already enrolled in engine');
    }

    private function makeEligibleAssignment(string $shipmentStatus = ShipmentStatus::ASSIGNED): Assignment
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId($shipmentStatus),
        ]);

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'status_id' => $this->assignmentStatus->id,
            'is_active' => true,
            'engine_enrollment_status' => null,
            'engine_enrolled_at' => null,
        ]);
    }
}
