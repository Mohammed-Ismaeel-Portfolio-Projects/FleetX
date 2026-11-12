<?php

namespace Tests\Feature\Simulation;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Vehicle;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simulation eligibility and tick regression tests.
 *
 * Proves that engine_enrollment_status controls SimulationRuntime
 * vehicle discovery and that unenrolled assignments are correctly skipped.
 */
class SimulationEligibilityAndTickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
    }

    // ── A. Vehicle eligibility ──────────────────────────────────────────────

    public function test_enrolled_active_assignment_is_simulation_eligible(): void
    {
        $assignment = $this->createAssignment(isActive: true, enrollment: EngineEnrollmentStatus::ENROLLED);

        $eligible = SimulationEligibleAssignments::query()->get();

        $this->assertCount(1, $eligible);
        $this->assertTrue($eligible->first()->is($assignment));
    }

    public function test_active_enrollment_is_simulation_eligible(): void
    {
        $assignment = $this->createAssignment(isActive: true, enrollment: EngineEnrollmentStatus::ACTIVE);

        $eligible = SimulationEligibleAssignments::query()->get();

        $this->assertCount(1, $eligible);
        $this->assertTrue($eligible->first()->is($assignment));
    }

    public function test_null_enrollment_is_not_simulation_eligible(): void
    {
        $this->createAssignment(isActive: true, enrollment: null);

        $eligible = SimulationEligibleAssignments::query()->get();

        $this->assertCount(0, $eligible);
    }

    public function test_completed_enrollment_is_not_simulation_eligible(): void
    {
        $this->createAssignment(isActive: true, enrollment: EngineEnrollmentStatus::COMPLETED);

        $eligible = SimulationEligibleAssignments::query()->get();

        $this->assertCount(0, $eligible);
    }

    public function test_cancelled_enrollment_is_not_simulation_eligible(): void
    {
        $this->createAssignment(isActive: true, enrollment: EngineEnrollmentStatus::CANCELLED);

        $eligible = SimulationEligibleAssignments::query()->get();

        $this->assertCount(0, $eligible);
    }

    public function test_inactive_assignment_is_not_eligible_even_if_enrolled(): void
    {
        $this->createAssignment(isActive: false, enrollment: EngineEnrollmentStatus::ENROLLED);

        $eligible = SimulationEligibleAssignments::query()->get();

        $this->assertCount(0, $eligible);
    }

    // ── D. No eligible vehicles ─────────────────────────────────────────────

    public function test_empty_database_returns_zero_eligible(): void
    {
        $eligible = SimulationEligibleAssignments::query()->get();

        $this->assertCount(0, $eligible);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createAssignment(bool $isActive, ?string $enrollment): Assignment
    {
        $status = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );

        return Assignment::factory()->create([
            'shipment_id' => Shipment::factory()->create([
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'status_id' => $status->id,
            'is_active' => $isActive,
            'engine_enrollment_status' => $enrollment,
            'engine_enrolled_at' => $enrollment ? now() : null,
        ]);
    }
}
