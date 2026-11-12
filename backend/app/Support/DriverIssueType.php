<?php

namespace App\Support;

/**
 * Driver-selectable essential issue reasons (D7).
 * Stable codes persisted on issues.type.
 */
final class DriverIssueType
{
    public const TRAFFIC = 'traffic';

    public const TRAFFIC_LIGHT = 'traffic_light';

    public const VEHICLE_PROBLEM = 'vehicle_problem';

    public const ACCIDENT = 'accident';

    public const LOADING_PROBLEM = 'loading_problem';

    public const DELIVERY_PROBLEM = 'delivery_problem';

    public const OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::TRAFFIC,
            self::TRAFFIC_LIGHT,
            self::VEHICLE_PROBLEM,
            self::ACCIDENT,
            self::LOADING_PROBLEM,
            self::DELIVERY_PROBLEM,
            self::OTHER,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    public static function requiresDescription(string $type): bool
    {
        return $type === self::OTHER;
    }
}
