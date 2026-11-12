<?php

namespace App\Support;

/**
 * Driver execution phases (D4) — assignment-scoped, not Client shipment statuses.
 */
final class DriverExecutionPhase
{
    public const PICKUP_PENDING = 'pickup_pending';

    public const PICKUP_ARRIVED = 'pickup_arrived';

    public const PICKUP_LOADING = 'pickup_loading';

    public const PICKUP_VERIFYING = 'pickup_verifying';

    public const PICKUP_VERIFIED = 'pickup_verified';

    public const PICKUP_FAILED = 'pickup_failed';

    public const DELIVERY_EN_ROUTE = 'delivery_en_route';

    public const DELIVERY_ARRIVED = 'delivery_arrived';

    public const DELIVERY_UNLOADING = 'delivery_unloading';

    public const DELIVERY_VERIFYING = 'delivery_verifying';

    public const DELIVERY_VERIFIED = 'delivery_verified';

    public const POD_SUBMITTED = 'pod_submitted';

    public const DELIVERY_FAILED = 'delivery_failed';

    /**
     * Driver-allowed transitions in D4 (no QR-complete edges).
     *
     * @return array<string, array<string, string>> command => [from => to]
     */
    public static function d4Commands(): array
    {
        return [
            'pickup_arrive' => [self::PICKUP_PENDING => self::PICKUP_ARRIVED],
            'pickup_loading' => [self::PICKUP_ARRIVED => self::PICKUP_LOADING],
            'pickup_loading_complete' => [self::PICKUP_LOADING => self::PICKUP_VERIFYING],
            'delivery_arrive' => [self::DELIVERY_EN_ROUTE => self::DELIVERY_ARRIVED],
            'delivery_unloading' => [self::DELIVERY_ARRIVED => self::DELIVERY_UNLOADING],
            'delivery_unloading_complete' => [self::DELIVERY_UNLOADING => self::DELIVERY_VERIFYING],
        ];
    }

    public static function stage(?string $phase): ?string
    {
        if ($phase === null) {
            return null;
        }

        if (str_starts_with($phase, 'pickup_')) {
            return 'pickup';
        }

        if (str_starts_with($phase, 'delivery_') || $phase === self::POD_SUBMITTED) {
            return 'delivery';
        }

        return null;
    }

    public static function isVerificationBoundary(?string $phase): bool
    {
        return in_array($phase, [self::PICKUP_VERIFYING, self::DELIVERY_VERIFYING], true);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PICKUP_PENDING,
            self::PICKUP_ARRIVED,
            self::PICKUP_LOADING,
            self::PICKUP_VERIFYING,
            self::PICKUP_VERIFIED,
            self::PICKUP_FAILED,
            self::DELIVERY_EN_ROUTE,
            self::DELIVERY_ARRIVED,
            self::DELIVERY_UNLOADING,
            self::DELIVERY_VERIFYING,
            self::DELIVERY_VERIFIED,
            self::POD_SUBMITTED,
            self::DELIVERY_FAILED,
        ];
    }

    public static function isPodEligible(?string $phase): bool
    {
        return in_array($phase, [self::DELIVERY_VERIFIED, self::POD_SUBMITTED], true);
    }

    public static function isCompleteEligiblePhase(?string $phase): bool
    {
        return $phase === self::POD_SUBMITTED;
    }
}
