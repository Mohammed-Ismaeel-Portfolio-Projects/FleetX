<?php

namespace App\Support;

use App\Models\Status;

/**
 * Canonical Shipment operational status names (type = shipment).
 *
 * Persistence vocabulary only — lifecycle changes belong in Shipment domain actions.
 */
final class ShipmentStatus
{
    public const TYPE = 'shipment';

    public const DRAFT = 'Draft';
    public const PENDING_REVIEW = 'Pending Review';
    public const APPROVED = 'Approved';
    public const WAITING_ASSIGNMENT = 'Waiting Assignment';
    public const ASSIGNED = 'Assigned';
    public const READY_FOR_PICKUP = 'Ready For Pickup';
    public const PICKED_UP = 'Picked Up';
    public const IN_TRANSIT = 'In Transit';
    public const AT_CUSTOMS = 'At Customs';
    public const OUT_FOR_DELIVERY = 'Out For Delivery';
    public const DELIVERED = 'Delivered';
    public const COMPLETED = 'Completed';
    public const CANCELLED = 'Cancelled';

    /**
     * Initial Shipment status after ShipmentOrder conversion (C2.9-B3.2 / B3.1 audit).
     */
    public const CONVERSION_INITIAL = self::WAITING_ASSIGNMENT;

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PENDING_REVIEW,
            self::APPROVED,
            self::WAITING_ASSIGNMENT,
            self::ASSIGNED,
            self::READY_FOR_PICKUP,
            self::PICKED_UP,
            self::IN_TRANSIT,
            self::AT_CUSTOMS,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }

    public static function statusId(string $name): int
    {
        return Status::where('name', $name)->where('type', self::TYPE)->firstOrFail()->id;
    }

    public static function conversionInitialStatusId(): int
    {
        return self::statusId(self::CONVERSION_INITIAL);
    }
}
