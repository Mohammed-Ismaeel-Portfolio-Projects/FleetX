<?php

namespace App\Support;

use App\Models\Status;

/**
 * Canonical driver dispatch availability (same rules as GetDriverAvailabilityAction).
 *
 * Official locked statuses: Offline, On Leave, Suspended.
 * Busy = active assignment and not locked.
 * Available = residual free capacity (not locked, no active assignment).
 */
final class DriverOperationalAvailability
{
    public const LOCKED = ['Offline', 'On Leave', 'Suspended'];

    public static function classify(?string $statusName, bool $hasActiveAssignment): string
    {
        if ($statusName !== null && in_array($statusName, self::LOCKED, true)) {
            return $statusName;
        }

        if ($hasActiveAssignment) {
            return 'Busy';
        }

        return 'Available';
    }

    public static function fromStatus(?Status $status, bool $hasActiveAssignment): string
    {
        return self::classify($status?->name, $hasActiveAssignment);
    }
}
