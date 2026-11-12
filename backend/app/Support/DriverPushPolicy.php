<?php

namespace App\Support;

/**
 * Which persisted Driver inbox codes warrant a device push (D12).
 * Does not invent codes. Self-generated execution events are excluded.
 */
final class DriverPushPolicy
{
    /**
     * @return list<string>
     */
    public static function pushableCodes(): array
    {
        return [
            DriverNotificationCode::ASSIGNMENT_ASSIGNED,
            DriverNotificationCode::ASSIGNMENT_CHANGED,
            DriverNotificationCode::ASSIGNMENT_REASSIGNED,
            DriverNotificationCode::ASSIGNMENT_CANCELLED,
            DriverNotificationCode::DELIVERY_FAILED,
            DriverNotificationCode::ISSUE_UPDATED,
            DriverNotificationCode::MESSAGE_UNREAD,
            DriverNotificationCode::SPEED_EXCESS,
            DriverNotificationCode::HARSH_BRAKING,
            DriverNotificationCode::SUDDEN_STOP,
        ];
    }

    public static function shouldPush(?string $code): bool
    {
        return is_string($code) && in_array($code, self::pushableCodes(), true);
    }
}
