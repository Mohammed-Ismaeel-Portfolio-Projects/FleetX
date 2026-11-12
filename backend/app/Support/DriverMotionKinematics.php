<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Live Driver longitudinal kinematics (D15-B).
 *
 * Observes consecutive TrackingLog.speed values (km/h). Does not simulate
 * motion and does not invoke the simulation dynamics tick.
 *
 * a_obs (m/s²) = (v1_m/s − v0_m/s) / Δt_s
 * Negative a_obs is braking.
 */
final class DriverMotionKinematics
{
    public const KMH_PER_MS = 3.6;

    /**
     * Convert device/D6 canonical km/h to metres per second for physics.
     */
    public static function kmhToMetersPerSecond(float $speedKmh): float
    {
        return $speedKmh / self::KMH_PER_MS;
    }

    /**
     * Convert metres per second back to km/h.
     */
    public static function metersPerSecondToKmh(float $speedMetersPerSecond): float
    {
        return $speedMetersPerSecond * self::KMH_PER_MS;
    }

    /**
     * Absolute elapsed seconds between two telemetry timestamps (Carbon 3 signed diffs).
     * Returns null when either timestamp cannot be parsed.
     */
    public static function elapsedSeconds(mixed $from, mixed $to): ?float
    {
        $start = self::parseTimestamp($from);
        $end = self::parseTimestamp($to);
        if ($start === null || $end === null) {
            return null;
        }

        return abs($end->diffInSeconds($start));
    }

    /**
     * Observed longitudinal acceleration from two speeds (km/h) and Δt (s).
     * Returns null for non-finite speeds or non-positive Δt (no silent invention).
     */
    public static function observe(
        float $speed0Kmh,
        float $speed1Kmh,
        float $deltaTimeSeconds
    ): ?ObservedLongitudinalMotion {
        if (! is_finite($speed0Kmh) || ! is_finite($speed1Kmh) || ! is_finite($deltaTimeSeconds)) {
            return null;
        }

        if ($deltaTimeSeconds <= 0.0) {
            return null;
        }

        $speed0Ms = self::kmhToMetersPerSecond($speed0Kmh);
        $speed1Ms = self::kmhToMetersPerSecond($speed1Kmh);
        $accelerationMs2 = ($speed1Ms - $speed0Ms) / $deltaTimeSeconds;

        return new ObservedLongitudinalMotion(
            speed0Kmh: $speed0Kmh,
            speed1Kmh: $speed1Kmh,
            speed0MetersPerSecond: $speed0Ms,
            speed1MetersPerSecond: $speed1Ms,
            deltaTimeSeconds: $deltaTimeSeconds,
            accelerationMs2: $accelerationMs2,
        );
    }

    /**
     * Stopping distance s = v² / (2a) with v in m/s and a = deceleration magnitude (m/s²).
     * Returns null if deceleration is not positive (would divide by zero / imply infinite distance).
     */
    public static function stoppingDistanceMeters(float $speedKmh, float $decelerationMagnitudeMs2): ?float
    {
        if (! is_finite($speedKmh) || ! is_finite($decelerationMagnitudeMs2)) {
            return null;
        }

        if ($decelerationMagnitudeMs2 <= 0.0) {
            return null;
        }

        $speedMs = self::kmhToMetersPerSecond(max(0.0, $speedKmh));

        return ($speedMs * $speedMs) / (2.0 * $decelerationMagnitudeMs2);
    }

    private static function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
