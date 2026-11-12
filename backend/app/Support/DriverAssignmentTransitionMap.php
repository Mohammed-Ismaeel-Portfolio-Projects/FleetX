<?php

namespace App\Support;

/**
 * Driver-allowed assignment status transitions (D3).
 * Uses AssignmentSeeder vocabulary only — does not invent statuses.
 */
final class DriverAssignmentTransitionMap
{
    public const ASSIGNED = 'Assigned';

    public const READY = 'Ready';

    public const IN_OPERATION = 'In Operation';

    public const COMPLETED = 'Completed';

    public const CANCELLED = 'Cancelled';

    /**
     * @return list<string>
     */
    public static function acknowledgeEligibleFrom(): array
    {
        return [self::ASSIGNED];
    }

    /**
     * @return list<string>
     */
    public static function startEligibleFrom(): array
    {
        return [self::READY];
    }

    public static function isTerminal(?string $statusName): bool
    {
        return in_array($statusName, [self::COMPLETED, self::CANCELLED], true);
    }
}
