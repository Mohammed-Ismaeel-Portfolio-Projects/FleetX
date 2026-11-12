<?php

use App\Support\EngineEnrollmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill engine_enrollment_status for active assignments that were created
 * before the enrollment column was added (2026_08_13_010000).
 *
 * Active assignments with a vehicle, driver, and shipment that have NULL
 * enrollment status are set to 'enrolled' so the SimulationRuntime can
 * discover them via SimulationEligibleAssignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('assignments')
            ->where('is_active', true)
            ->whereNull('engine_enrollment_status')
            ->whereNotNull('vehicle_id')
            ->whereNotNull('driver_id')
            ->whereNotNull('shipment_id')
            ->update([
                'engine_enrollment_status' => EngineEnrollmentStatus::ENROLLED,
                'engine_enrolled_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Reversal would require tracking which rows were backfilled.
        // Safe no-op: the column itself is dropped by the parent migration's down().
    }
};
