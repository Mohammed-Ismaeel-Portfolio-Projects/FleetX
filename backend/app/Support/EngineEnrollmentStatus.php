<?php

namespace App\Support;

/**
 * Engine enrollment states for Assignments (C2.9-C2.8 Step 3).
 *
 * Distinct from Assignment.status_id and Shipment lifecycle.
 */
final class EngineEnrollmentStatus
{
    public const ENROLLED = 'enrolled';

    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /**
     * Statuses eligible for SimulationRuntime participation.
     *
     * @return list<string>
     */
    public static function simulationEligible(): array
    {
        return [
            self::ENROLLED,
            self::ACTIVE,
        ];
    }

    public static function isSimulationEligible(?string $status): bool
    {
        return $status !== null && in_array($status, self::simulationEligible(), true);
    }
}
