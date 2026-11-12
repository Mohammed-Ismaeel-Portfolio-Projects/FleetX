<?php

namespace App\Support;

/**
 * Authoritative Shipment operational transition map (C2.9-C1.4 MVP).
 *
 * All lifecycle status changes must pass through TransitionShipmentStatusAction.
 */
final class ShipmentTransitionMap
{
    /**
     * @return list<string>
     */
    public static function allowedTargets(string $fromStatusName): array
    {
        return match ($fromStatusName) {
            ShipmentStatus::WAITING_ASSIGNMENT => [ShipmentStatus::ASSIGNED],
            ShipmentStatus::ASSIGNED => [ShipmentStatus::IN_TRANSIT],
            ShipmentStatus::IN_TRANSIT => [ShipmentStatus::DELIVERED],
            ShipmentStatus::DELIVERED => [ShipmentStatus::COMPLETED],
            default => [],
        };
    }

    public static function canTransition(string $fromStatusName, string $toStatusName): bool
    {
        return in_array($toStatusName, self::allowedTargets($fromStatusName), true);
    }
}
