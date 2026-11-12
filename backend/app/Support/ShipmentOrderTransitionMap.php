<?php

namespace App\Support;

/**
 * Authoritative Shipment Order lifecycle transition map (C2.9-A3.2 MVP).
 *
 * All status changes must pass through TransitionShipmentOrderStatusAction.
 */
final class ShipmentOrderTransitionMap
{
    /**
     * @return list<string>
     */
    public static function allowedTargets(string $fromStatusName): array
    {
        return match ($fromStatusName) {
            ShipmentOrderStatus::DRAFT => [ShipmentOrderStatus::SUBMITTED],
            ShipmentOrderStatus::SUBMITTED => [
                ShipmentOrderStatus::UNDER_REVIEW,
                ShipmentOrderStatus::CANCELLED,
            ],
            ShipmentOrderStatus::UNDER_REVIEW => [
                ShipmentOrderStatus::CHANGES_REQUESTED,
                ShipmentOrderStatus::APPROVED,
                ShipmentOrderStatus::REJECTED,
            ],
            ShipmentOrderStatus::CHANGES_REQUESTED => [
                ShipmentOrderStatus::SUBMITTED,
                ShipmentOrderStatus::CANCELLED,
            ],
            ShipmentOrderStatus::APPROVED => [ShipmentOrderStatus::CONVERTED],
            ShipmentOrderStatus::REJECTED,
            ShipmentOrderStatus::CANCELLED,
            ShipmentOrderStatus::CONVERTED => [],
            default => [],
        };
    }

    public static function isTerminal(string $statusName): bool
    {
        return in_array($statusName, [
            ShipmentOrderStatus::REJECTED,
            ShipmentOrderStatus::CANCELLED,
            ShipmentOrderStatus::CONVERTED,
        ], true);
    }

    public static function canTransition(string $fromStatusName, string $toStatusName): bool
    {
        return in_array($toStatusName, self::allowedTargets($fromStatusName), true);
    }
}
