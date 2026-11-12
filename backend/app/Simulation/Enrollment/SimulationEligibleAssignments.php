<?php

namespace App\Simulation\Enrollment;

use App\Models\Assignment;
use App\Support\EngineEnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single query boundary for Assignments allowed into SimulationRuntime (C2.9-C2.8 Step 3).
 *
 * Replaces implicit Assignment::active()-only selection.
 */
final class SimulationEligibleAssignments
{
    /**
     * Active + engine-enrolled assignments.
     *
     * @return Builder<Assignment>
     */
    public static function query(): Builder
    {
        return Assignment::query()
            ->active()
            ->whereIn('engine_enrollment_status', EngineEnrollmentStatus::simulationEligible());
    }
}
