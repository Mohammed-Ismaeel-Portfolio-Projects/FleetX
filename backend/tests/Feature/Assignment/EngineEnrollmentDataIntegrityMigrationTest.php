<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Corrective migration: normalize invalid engine enrollment on Waiting Assignment shipments.
 */
class EngineEnrollmentDataIntegrityMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = 'database/migrations/2026_08_18_020000_normalize_invalid_engine_enrollment_for_waiting_assignment_shipments.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentStatusesSeeder::class);
    }

    public function test_valid_assigned_enrollment_preserved(): void
    {
        $assignment = $this->createAssignment(
            shipmentStatus: ShipmentStatus::ASSIGNED,
            enrollment: EngineEnrollmentStatus::ENROLLED,
            isActive: true,
        );

        $this->runNormalizationMigration();

        $assignment->refresh();
        $this->assertSame(EngineEnrollmentStatus::ENROLLED, $assignment->engine_enrollment_status);
        $this->assertNotNull($assignment->engine_enrolled_at);
        $this->assertTrue(SimulationEligibleAssignments::query()->whereKey($assignment->id)->exists());
    }

    public function test_enrolled_waiting_assignment_normalized_to_null(): void
    {
        $assignment = $this->createAssignment(
            shipmentStatus: ShipmentStatus::WAITING_ASSIGNMENT,
            enrollment: EngineEnrollmentStatus::ENROLLED,
            isActive: true,
        );

        $this->runNormalizationMigration();

        $assignment->refresh();
        $this->assertNull($assignment->engine_enrollment_status);
        $this->assertNull($assignment->engine_enrolled_at);
        $this->assertFalse(SimulationEligibleAssignments::query()->whereKey($assignment->id)->exists());
    }

    public function test_active_enrollment_waiting_assignment_normalized(): void
    {
        $assignment = $this->createAssignment(
            shipmentStatus: ShipmentStatus::WAITING_ASSIGNMENT,
            enrollment: EngineEnrollmentStatus::ACTIVE,
            isActive: true,
        );

        $this->runNormalizationMigration();

        $assignment->refresh();
        $this->assertNull($assignment->engine_enrollment_status);
        $this->assertNull($assignment->engine_enrolled_at);
    }

    public function test_inactive_invalid_enrollment_not_modified(): void
    {
        $assignment = $this->createAssignment(
            shipmentStatus: ShipmentStatus::WAITING_ASSIGNMENT,
            enrollment: EngineEnrollmentStatus::ENROLLED,
            isActive: false,
        );

        $this->runNormalizationMigration();

        $assignment->refresh();
        $this->assertSame(EngineEnrollmentStatus::ENROLLED, $assignment->engine_enrollment_status);
        $this->assertNotNull($assignment->engine_enrolled_at);
    }

    public function test_enrolled_in_transit_preserved_as_post_enrollment_lifecycle(): void
    {
        $assignment = $this->createAssignment(
            shipmentStatus: ShipmentStatus::IN_TRANSIT,
            enrollment: EngineEnrollmentStatus::ACTIVE,
            isActive: true,
        );

        $this->runNormalizationMigration();

        $assignment->refresh();
        $this->assertSame(EngineEnrollmentStatus::ACTIVE, $assignment->engine_enrollment_status);
        $this->assertNotNull($assignment->engine_enrolled_at);
        $this->assertTrue(SimulationEligibleAssignments::query()->whereKey($assignment->id)->exists());
    }

    public function test_migration_completes_without_error_when_no_invalid_rows(): void
    {
        $this->createAssignment(
            shipmentStatus: ShipmentStatus::ASSIGNED,
            enrollment: EngineEnrollmentStatus::ENROLLED,
            isActive: true,
        );

        $this->runNormalizationMigration();

        $this->assertTrue(true);
    }

    public function test_migration_is_idempotent(): void
    {
        $assignment = $this->createAssignment(
            shipmentStatus: ShipmentStatus::WAITING_ASSIGNMENT,
            enrollment: EngineEnrollmentStatus::ENROLLED,
            isActive: true,
        );

        $this->runNormalizationMigration();
        $this->runNormalizationMigration();

        $assignment->refresh();
        $this->assertNull($assignment->engine_enrollment_status);
        $this->assertNull($assignment->engine_enrolled_at);
    }

    private function createAssignment(
        string $shipmentStatus,
        string $enrollment,
        bool $isActive,
    ): Assignment {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId($shipmentStatus),
        ]);

        $assignmentStatus = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => \App\Models\Vehicle::factory()->create()->id,
            'status_id' => $assignmentStatus->id,
            'is_active' => $isActive,
            'engine_enrollment_status' => $enrollment,
            'engine_enrolled_at' => now(),
        ]);
    }

    private function runNormalizationMigration(): void
    {
        $migration = require base_path(self::MIGRATION_PATH);
        $migration->up();
    }
}
