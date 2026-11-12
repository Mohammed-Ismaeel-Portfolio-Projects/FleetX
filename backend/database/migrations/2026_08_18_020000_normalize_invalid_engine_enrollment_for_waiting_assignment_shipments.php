<?php

use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrective data migration — invalid engine enrollment from 2026_08_18_010000 backfill.
 *
 * The backfill enrolled active assignments without validating shipment lifecycle.
 * EngineEnrollmentEligibility requires ShipmentStatus::ASSIGNED at enrollment time;
 * Waiting Assignment cannot be enrolled via EnrollAssignmentInEngineAction.
 *
 * This migration revokes only impossible legacy rows:
 * active + (enrolled|active) + shipment.status = Waiting Assignment.
 *
 * Valid enrollments (e.g. Assigned, or post-enrollment In Transit) are preserved.
 * SimulationEligibleAssignments query contract is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        $waitingStatusId = DB::table('statuses')
            ->where('type', ShipmentStatus::TYPE)
            ->where('name', ShipmentStatus::WAITING_ASSIGNMENT)
            ->value('id');

        if ($waitingStatusId === null) {
            return;
        }

        DB::table('assignments')
            ->where('is_active', true)
            ->whereIn('engine_enrollment_status', EngineEnrollmentStatus::simulationEligible())
            ->whereNotNull('shipment_id')
            ->whereIn('shipment_id', function ($query) use ($waitingStatusId) {
                $query->select('id')
                    ->from('shipments')
                    ->where('status_id', $waitingStatusId);
            })
            ->update([
                'engine_enrollment_status' => null,
                'engine_enrolled_at' => null,
            ]);
    }

    public function down(): void
    {
        // Cannot safely restore which rows were corrected without audit trail.
    }
};
