<?php

namespace App\Support;

/**
 * D14 speed-safety policy. Canonical unit: km/h (same as D6 device telemetry).
 */
final class DriverSpeedSafetyPolicy
{
    public static function enabled(): bool
    {
        return (bool) config('driver_safety.speed.enabled', true);
    }

    public static function thresholdKmh(): float
    {
        return max(1.0, (float) config('driver_safety.speed.threshold_kmh', 110));
    }

    public static function minSamples(): int
    {
        return max(2, (int) config('driver_safety.speed.min_samples', 3));
    }

    public static function minDurationSeconds(): int
    {
        return max(1, (int) config('driver_safety.speed.min_duration_seconds', 10));
    }

    public static function cooldownSeconds(): int
    {
        return max(1, (int) config('driver_safety.speed.cooldown_seconds', 300));
    }

    public static function recoveryKmh(): float
    {
        $recovery = (float) config('driver_safety.speed.recovery_kmh', 95);
        $threshold = self::thresholdKmh();

        return min($recovery, $threshold);
    }

    public static function maxAccuracyMeters(): float
    {
        return max(1.0, (float) config('driver_safety.speed.max_accuracy_meters', 50));
    }

    public static function maxSampleAgeSeconds(): int
    {
        return max(1, (int) config('driver_safety.speed.max_sample_age_seconds', 30));
    }

    public static function maxPlausibleKmh(): float
    {
        return max(self::thresholdKmh(), (float) config('driver_safety.speed.max_plausible_kmh', 200));
    }

    public static function lookbackSeconds(): int
    {
        return max(self::minDurationSeconds(), (int) config('driver_safety.speed.lookback_seconds', 120));
    }
}
