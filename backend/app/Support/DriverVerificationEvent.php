<?php

namespace App\Support;

/**
 * Driver QR verification challenge events (D5).
 */
final class DriverVerificationEvent
{
    public const PICKUP_VERIFY = 'pickup_verify';

    public const DELIVERY_VERIFY = 'delivery_verify';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PICKUP_VERIFY, self::DELIVERY_VERIFY];
    }

    public static function requiredPhase(string $event): ?string
    {
        return match ($event) {
            self::PICKUP_VERIFY => DriverExecutionPhase::PICKUP_VERIFYING,
            self::DELIVERY_VERIFY => DriverExecutionPhase::DELIVERY_VERIFYING,
            default => null,
        };
    }

    public static function fromPhase(?string $phase): ?string
    {
        return match ($phase) {
            DriverExecutionPhase::PICKUP_VERIFYING => self::PICKUP_VERIFY,
            DriverExecutionPhase::DELIVERY_VERIFYING => self::DELIVERY_VERIFY,
            default => null,
        };
    }
}
