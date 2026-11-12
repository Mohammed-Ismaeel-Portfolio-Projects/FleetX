<?php

namespace App\Support\Client;

use Illuminate\Support\Carbon;

/**
 * Client-safe location freshness projection for shipment tracking (C5.5-C2.3).
 *
 * Derived only from already-projected last_known_position.recorded_at.
 * Thresholds: fresh < 5m; stale 5–30m inclusive; very_stale > 30m.
 */
final class ClientShipmentLocationFreshness
{
    private const FRESH_BELOW_SECONDS = 300;

    private const STALE_MAX_SECONDS = 1800;

    /**
     * @param  array{latitude?: mixed, longitude?: mixed, recorded_at?: mixed}|null  $lastKnownPosition
     * @return array{state: string, recorded_at: string|null, age_seconds: int}
     */
    public static function fromLastKnownPosition(?array $lastKnownPosition): array
    {
        if ($lastKnownPosition === null) {
            return self::unavailable();
        }

        $recordedAtRaw = $lastKnownPosition['recorded_at'] ?? null;
        if (! is_string($recordedAtRaw) || trim($recordedAtRaw) === '') {
            return self::unavailable();
        }

        try {
            $recordedAt = Carbon::parse($recordedAtRaw);
        } catch (\Throwable) {
            return self::unavailable();
        }

        $now = Carbon::now();
        $ageSeconds = $recordedAt->greaterThan($now)
            ? 0
            : (int) $recordedAt->diffInSeconds($now);

        $formatted = $recordedAt->format('Y-m-d H:i:s');

        if ($ageSeconds < self::FRESH_BELOW_SECONDS) {
            return [
                'state' => 'fresh',
                'recorded_at' => $formatted,
                'age_seconds' => $ageSeconds,
            ];
        }

        if ($ageSeconds <= self::STALE_MAX_SECONDS) {
            return [
                'state' => 'stale',
                'recorded_at' => $formatted,
                'age_seconds' => $ageSeconds,
            ];
        }

        return [
            'state' => 'very_stale',
            'recorded_at' => $formatted,
            'age_seconds' => $ageSeconds,
        ];
    }

    /**
     * @return array{state: string, recorded_at: null, age_seconds: int}
     */
    private static function unavailable(): array
    {
        return [
            'state' => 'unavailable',
            'recorded_at' => null,
            'age_seconds' => 0,
        ];
    }
}
