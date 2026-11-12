<?php

namespace App\Support;

/**
 * Stable Driver notification codes (D8).
 */
final class DriverNotificationCode
{
    public const ASSIGNMENT_ASSIGNED = 'assignment.assigned';

    public const ASSIGNMENT_CHANGED = 'assignment.changed';

    public const ASSIGNMENT_REASSIGNED = 'assignment.reassigned';

    public const ASSIGNMENT_CANCELLED = 'assignment.cancelled';

    public const PICKUP_ARRIVAL = 'pickup.arrival';

    public const DELIVERY_ARRIVAL = 'delivery.arrival';

    public const PICKUP_VERIFICATION_SUCCESS = 'pickup.verification_success';

    public const DELIVERY_VERIFICATION_SUCCESS = 'delivery.verification_success';

    public const DELIVERY_COMPLETED = 'delivery.completed';

    public const DELIVERY_FAILED = 'delivery.failed';

    public const ISSUE_UPDATED = 'issue.updated';

    public const MESSAGE_UNREAD = 'message.unread';

    public const SPEED_EXCESS = 'safety.speed_excess';

    public const HARSH_BRAKING = 'safety.harsh_braking';

    public const SUDDEN_STOP = 'safety.sudden_stop';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ASSIGNMENT_ASSIGNED,
            self::ASSIGNMENT_CHANGED,
            self::ASSIGNMENT_REASSIGNED,
            self::ASSIGNMENT_CANCELLED,
            self::PICKUP_ARRIVAL,
            self::DELIVERY_ARRIVAL,
            self::PICKUP_VERIFICATION_SUCCESS,
            self::DELIVERY_VERIFICATION_SUCCESS,
            self::DELIVERY_COMPLETED,
            self::DELIVERY_FAILED,
            self::ISSUE_UPDATED,
            self::MESSAGE_UNREAD,
            self::SPEED_EXCESS,
            self::HARSH_BRAKING,
            self::SUDDEN_STOP,
        ];
    }
}
