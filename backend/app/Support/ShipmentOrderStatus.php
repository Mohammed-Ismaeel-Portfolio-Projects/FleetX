<?php

namespace App\Support;

use App\Models\Status;

/**
 * Canonical Shipment Order lifecycle status names (type = shipment_order).
 *
 * Persistence vocabulary only — no transition methods. State changes belong in domain actions (C2.9-A3.x).
 */
final class ShipmentOrderStatus
{
    public const TYPE = 'shipment_order';

    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const UNDER_REVIEW = 'under_review';
    public const CHANGES_REQUESTED = 'changes_requested';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';
    public const CONVERTED = 'converted';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::UNDER_REVIEW,
            self::CHANGES_REQUESTED,
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
            self::CONVERTED,
        ];
    }

    public static function statusId(string $name): int
    {
        return Status::where('name', $name)->where('type', self::TYPE)->firstOrFail()->id;
    }
}
