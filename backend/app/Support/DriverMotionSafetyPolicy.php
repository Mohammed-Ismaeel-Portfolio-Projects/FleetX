<?php

namespace App\Support;

use App\Models\Assignment;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;
use App\Simulation\Routing\Application\Models\VehicleProfile;

/**
 * D15-B live motion policy. Canonical physics unit: m/s².
 * Speed inputs remain D6 km/h. This is NOT legal brake regulation.
 *
 * Simulation VehicleConstraints maxBrakingMs2 (0.15–0.22) MUST NOT be used here.
 */
final class DriverMotionSafetyPolicy
{
    /**
     * @return list<string>
     */
    public static function freightClasses(): array
    {
        return [
            VehicleProfile::FREIGHT_VAN,
            VehicleProfile::FREIGHT_LIGHT_TRUCK,
            VehicleProfile::FREIGHT_HEAVY_TRUCK,
        ];
    }

    public static function enabled(): bool
    {
        return (bool) config('driver_safety.motion.enabled', true);
    }

    /**
     * Reuse the engine's canonical normalizer — do not invent a second classifier.
     */
    public static function normalizeFreightClass(?string $routingClass, int $weightKg = 0): string
    {
        return VehicleProfileFactory::normalizeFreightClass($routingClass, $weightKg);
    }

    /**
     * Authoritative class from the assigned vehicle. Never from a client payload.
     */
    public static function freightClassForAssignment(Assignment $assignment): string
    {
        $assignment->loadMissing(['vehicle', 'trailer']);
        $profile = VehicleProfileFactory::fromAssignment($assignment);

        return self::normalizeFreightClass(
            $profile->freightClass,
            (int) ($profile->weight ?? 0)
        );
    }

    public static function minSamples(): int
    {
        return max(2, (int) config('driver_safety.motion.min_samples', 3));
    }

    public static function minDurationSeconds(): int
    {
        return max(1, (int) config('driver_safety.motion.min_duration_seconds', 10));
    }

    public static function cooldownSeconds(): int
    {
        return max(1, (int) config('driver_safety.motion.cooldown_seconds', 300));
    }

    public static function minIntervalSeconds(): float
    {
        return max(0.001, (float) config('driver_safety.motion.min_interval_seconds', 3));
    }

    public static function maxIntervalSeconds(): float
    {
        $max = (float) config('driver_safety.motion.max_interval_seconds', 15);
        $min = self::minIntervalSeconds();

        return max($min, $max);
    }

    public static function maxPlausibleDecelerationMs2(): float
    {
        return max(1.0, (float) config('driver_safety.motion.max_plausible_deceleration_ms2', 8.0));
    }

    public static function maxAccuracyMeters(): float
    {
        return max(1.0, (float) config('driver_safety.motion.max_accuracy_meters', 50));
    }

    public static function maxSampleAgeSeconds(): int
    {
        return max(1, (int) config('driver_safety.motion.max_sample_age_seconds', 30));
    }

    public static function lookbackSeconds(): int
    {
        return max(self::minDurationSeconds(), (int) config('driver_safety.motion.lookback_seconds', 120));
    }

    /**
     * Mean |a| (m/s²) over a confirmed window that D15-C may treat as harsh braking.
     * Class-relative: heavier vehicles use a lower mean because 5 s GPS averaging
     * already under-samples peak g, and vans can slow more sharply in normal use.
     */
    public static function harshBrakeThresholdMs2(?string $freightClass = null): float
    {
        $class = self::normalizeFreightClass($freightClass);
        $defaults = [
            VehicleProfile::FREIGHT_VAN => 2.5,
            VehicleProfile::FREIGHT_LIGHT_TRUCK => 2.0,
            VehicleProfile::FREIGHT_HEAVY_TRUCK => 1.5,
        ];
        $configured = config('driver_safety.motion.harsh_brake_ms2.'.$class);
        $value = $configured !== null ? (float) $configured : $defaults[$class];

        return max(1.0, $value);
    }

    /**
     * After an episode, |a| at or below this is “recovered” (gentle slowing / noise).
     */
    public static function recoveryDecelerationMs2(): float
    {
        $recovery = (float) config('driver_safety.motion.recovery_deceleration_ms2', 0.5);
        $floor = min(self::harshBrakeThresholdMs2(VehicleProfile::FREIGHT_HEAVY_TRUCK), $recovery);

        return max(0.0, $floor);
    }

    public static function suddenStopMovingMinKmh(): float
    {
        return max(0.0, (float) config('driver_safety.motion.sudden_stop.moving_min_kmh', 30));
    }

    public static function suddenStopStoppedMaxKmh(): float
    {
        $stopped = (float) config('driver_safety.motion.sudden_stop.stopped_max_kmh', 5);
        $moving = self::suddenStopMovingMinKmh();

        return max(0.0, min($stopped, $moving));
    }

    public static function suddenStopMinSpeedDropKmh(): float
    {
        return max(0.0, (float) config('driver_safety.motion.sudden_stop.min_speed_drop_kmh', 25));
    }

    /**
     * Pairing two speeds is physically usable for a_obs.
     */
    public static function isUsableInterval(?float $deltaTimeSeconds): bool
    {
        if ($deltaTimeSeconds === null || ! is_finite($deltaTimeSeconds)) {
            return false;
        }

        return $deltaTimeSeconds >= self::minIntervalSeconds()
            && $deltaTimeSeconds <= self::maxIntervalSeconds();
    }

    /**
     * |a| above this is treated as GPS teleport, not braking (D15-C).
     */
    public static function isPhysicallyPlausibleDeceleration(float $decelerationMagnitudeMs2): bool
    {
        if (! is_finite($decelerationMagnitudeMs2) || $decelerationMagnitudeMs2 < 0.0) {
            return false;
        }

        return $decelerationMagnitudeMs2 <= self::maxPlausibleDecelerationMs2();
    }

    /**
     * Live Driver ingest sets received_at. Simulation TrackingLog writes do not.
     */
    public static function isLiveDriverEvidence(mixed $receivedAt): bool
    {
        if ($receivedAt === null) {
            return false;
        }

        if (is_string($receivedAt) && trim($receivedAt) === '') {
            return false;
        }

        return true;
    }

    /**
     * Missing/non-finite speed cannot participate in D15 (same fail-closed as D14).
     */
    public static function hasUsableSpeed(mixed $speedKmh): bool
    {
        if ($speedKmh === null || $speedKmh === '') {
            return false;
        }

        if (! is_numeric($speedKmh)) {
            return false;
        }

        return is_finite((float) $speedKmh);
    }
}
