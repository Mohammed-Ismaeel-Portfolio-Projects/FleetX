<?php

namespace App\Support;

/**
 * Driver App self-service availability (D13).
 *
 * Product keys available|unavailable map onto canonical driver statuses:
 * Available ↔ Offline. Never writes Busy / On Leave / Suspended / is_active.
 */
final class DriverSelfServiceAvailability
{
    public const AVAILABLE = 'available';

    public const UNAVAILABLE = 'unavailable';

    public const CANONICAL_AVAILABLE = 'Available';

    public const CANONICAL_UNAVAILABLE = 'Offline';

    public const BLOCKED_DURING_TRIP = 'DRIVER_AVAILABILITY_BLOCKED_DURING_TRIP';

    public const LOCKED = 'DRIVER_AVAILABILITY_LOCKED';

    /** Ops-managed statuses the Driver cannot self-clear. Offline is self-service. */
    public const OPS_LOCKED = ['On Leave', 'Suspended'];

    /**
     * @return self::AVAILABLE|self::UNAVAILABLE
     */
    public static function keyFromStatusName(?string $name, bool $isActive): string
    {
        if (! $isActive) {
            return self::UNAVAILABLE;
        }

        if ($name !== null && in_array($name, DriverOperationalAvailability::LOCKED, true)) {
            return self::UNAVAILABLE;
        }

        return self::AVAILABLE;
    }

    public static function isOpsLocked(?string $name, bool $isActive): bool
    {
        if (! $isActive) {
            return true;
        }

        return $name !== null && in_array($name, self::OPS_LOCKED, true);
    }

    public static function canonicalStatusName(string $availability): string
    {
        return $availability === self::UNAVAILABLE
            ? self::CANONICAL_UNAVAILABLE
            : self::CANONICAL_AVAILABLE;
    }

    /**
     * @return array{availability: string, can_change: bool, reason: string|null}
     */
    public static function snapshot(?string $statusName, bool $isActive, bool $hasActiveAssignment): array
    {
        $availability = self::keyFromStatusName($statusName, $isActive);

        if (self::isOpsLocked($statusName, $isActive)) {
            return [
                'availability' => $availability,
                'can_change' => false,
                'reason' => self::LOCKED,
            ];
        }

        if ($hasActiveAssignment) {
            return [
                'availability' => $availability,
                'can_change' => false,
                'reason' => self::BLOCKED_DURING_TRIP,
            ];
        }

        return [
            'availability' => $availability,
            'can_change' => true,
            'reason' => null,
        ];
    }
}
