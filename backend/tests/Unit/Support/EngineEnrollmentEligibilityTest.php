<?php

namespace Tests\Unit\Support;

use App\Models\Assignment;
use App\Models\Shipment;
use App\Support\EngineEnrollmentEligibility;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C2.9-C2.8 Step 7 — shared enrollment eligibility (backend source of truth).
 */
class EngineEnrollmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShipmentStatusesSeeder::class);
    }

    public function test_assigned_complete_assignment_can_enroll(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
        ]);
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'is_active' => true,
            'engine_enrollment_status' => null,
        ])->fresh(['shipment.status', 'vehicle', 'driver']);

        $result = EngineEnrollmentEligibility::assess($assignment);
        $this->assertTrue($result['can_enroll']);
        $this->assertFalse($result['already_enrolled']);
        $this->assertNull($result['block_reason']);
    }

    public function test_in_transit_shipment_cannot_enroll(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ]);
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'is_active' => true,
        ])->fresh(['shipment.status', 'vehicle', 'driver']);

        $result = EngineEnrollmentEligibility::assess($assignment);
        $this->assertFalse($result['can_enroll']);
        $this->assertStringContainsString('Assigned', (string) $result['block_reason']);
    }

    public function test_already_enrolled_is_not_can_enroll(): void
    {
        $shipment = Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
        ]);
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'is_active' => true,
            'engine_enrollment_status' => EngineEnrollmentStatus::ENROLLED,
            'engine_enrolled_at' => now(),
        ])->fresh(['shipment.status', 'vehicle', 'driver']);

        $result = EngineEnrollmentEligibility::assess($assignment);
        $this->assertFalse($result['can_enroll']);
        $this->assertTrue($result['already_enrolled']);
    }
}
