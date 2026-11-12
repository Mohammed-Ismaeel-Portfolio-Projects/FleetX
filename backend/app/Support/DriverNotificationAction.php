<?php

namespace App\Support;

/**
 * Driver notification deep-link allowlist (D8).
 */
final class DriverNotificationAction
{
    public const KIND_ASSIGNMENT = 'assignment';

    public const KIND_ACTIVE_TRIP = 'active_trip';

    public const KIND_CONVERSATION = 'conversation';

    public const KIND_WORK = 'work';

    /**
     * @var list<string>
     */
    public const ALLOWED_KINDS = [
        self::KIND_ASSIGNMENT,
        self::KIND_ACTIVE_TRIP,
        self::KIND_CONVERSATION,
        self::KIND_WORK,
    ];

    public static function isValidKind(?string $kind): bool
    {
        return is_string($kind) && in_array($kind, self::ALLOWED_KINDS, true);
    }
}
