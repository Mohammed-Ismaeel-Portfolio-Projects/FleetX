<?php

namespace App\Support;

/**
 * Derived Driver history outcome (D11).
 * Does not invent assignment statuses — maps terminal Completed/Cancelled + phase.
 */
final class DriverHistoryOutcome
{
    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    public static function key(?string $statusName, ?string $phase): string
    {
        if ($statusName === DriverAssignmentTransitionMap::COMPLETED) {
            return self::COMPLETED;
        }

        if ($statusName === DriverAssignmentTransitionMap::CANCELLED) {
            if (in_array($phase, [
                DriverExecutionPhase::DELIVERY_FAILED,
                DriverExecutionPhase::PICKUP_FAILED,
            ], true)) {
                return self::FAILED;
            }

            return self::CANCELLED;
        }

        return 'unavailable';
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::FAILED => 'Failed',
            default => 'Unavailable',
        };
    }

    /**
     * @return array{key: string, label: string}
     */
    public static function fromAssignment(?string $statusName, ?string $phase): array
    {
        $key = self::key($statusName, $phase);

        return [
            'key' => $key,
            'label' => self::label($key),
        ];
    }
}
