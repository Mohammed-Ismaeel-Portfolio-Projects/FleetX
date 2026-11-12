<?php

namespace App\Simulation\Environment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * EnvironmentEngine
 *
 * Deterministic road conditions / traffic / events.
 * No GPS, routing, or database persistence.
 */
class EnvironmentEngine
{
    public const CACHE_PREFIX = 'simulation:environment:';

    private const CACHE_TTL_SECONDS = 86400;

    /** Max traffic-density change per tick (smooth evolution). */
    private const TRAFFIC_MAX_STEP = 0.08;

    /**
     * Road event speed multipliers (affect recommended target only).
     *
     * @var array<string, float>
     */
    private const EVENT_SPEED_MULTIPLIERS = [
        'none' => 1.0,
        'light_traffic' => 0.92,
        'heavy_traffic' => 0.55,
        'construction' => 0.40,
        'accident' => 0.25,
        'road_closure' => 0.0,
        'rest_area' => 0.15,
        'border_crossing' => 0.20,
        'school_zone' => 0.35,
        'toll_gate' => 0.30,
    ];

    public function compute(
        string $vehicleId,
        string $routeId,
        int $tickIndex = 0,
        ?string $timestamp = null,
    ): EnvironmentSnapshot {
        $timestamp ??= now()->toIso8601String();
        $previous = $this->load($vehicleId);

        $roadSpeedLimit = $this->resolveRoadSpeedLimit($routeId, $vehicleId);
        $trafficDensity = $this->evolveTrafficDensity($vehicleId, $routeId, $tickIndex, $previous?->trafficDensity);
        $trafficFactor = $this->trafficFactorFromDensity($trafficDensity);

        $event = $this->resolveRoadEvent($vehicleId, $routeId, $tickIndex, $trafficDensity);
        $flags = $this->eventFlags($event);
        $weather = $this->resolveWeather($vehicleId, $tickIndex);
        $roadCondition = $this->resolveRoadCondition($weather);

        $eventMultiplier = self::EVENT_SPEED_MULTIPLIERS[$event] ?? 1.0;
        $weatherMultiplier = match ($weather) {
            'fog' => 0.85,
            'rain' => 0.90,
            'snow' => 0.75,
            default => 1.0,
        };

        $recommended = $roadSpeedLimit * $trafficFactor * $eventMultiplier * $weatherMultiplier;
        $recommended = max(0.0, min($roadSpeedLimit, $recommended));

        // School / toll / border flags can further taper without changing dynamics architecture.
        if ($flags['school_zone'] && $event === 'none') {
            $recommended = min($recommended, 30.0);
        }

        $snapshot = new EnvironmentSnapshot(
            routeId: $routeId,
            vehicleId: $vehicleId,
            timestamp: $timestamp,
            roadSpeedLimitKmh: $roadSpeedLimit,
            trafficDensity: round($trafficDensity, 4),
            trafficFactor: round($trafficFactor, 4),
            weatherCondition: $weather,
            roadCondition: $roadCondition,
            roadEvent: $event === 'none' ? null : $event,
            constructionZone: $flags['construction_zone'],
            accidentNearby: $flags['accident_nearby'],
            roadClosed: $flags['road_closed'],
            schoolZone: $flags['school_zone'],
            tollGateAhead: $flags['toll_gate_ahead'],
            borderCrossingAhead: $flags['border_crossing_ahead'],
            recommendedTargetSpeedKmh: round($recommended, 2),
        );

        $this->store($snapshot);

        Log::info('Environment conditions', [
            'vehicle_id' => $vehicleId,
            'traffic_density' => $snapshot->trafficDensity,
            'road_speed_limit_kmh' => $snapshot->roadSpeedLimitKmh,
            'recommended_target_speed_kmh' => $snapshot->recommendedTargetSpeedKmh,
            'road_event' => $snapshot->roadEvent,
        ]);

        return $snapshot;
    }

    public function load(string $vehicleId): ?EnvironmentSnapshot
    {
        $data = Cache::get(self::CACHE_PREFIX . $vehicleId);
        if (!is_array($data)) {
            return null;
        }

        return EnvironmentSnapshot::fromArray($data);
    }

    public function store(EnvironmentSnapshot $snapshot): void
    {
        Cache::put(
            self::CACHE_PREFIX . $snapshot->vehicleId,
            $snapshot->toArray(),
            self::CACHE_TTL_SECONDS
        );
    }

    /**
     * Deterministic seeded noise + clamp toward previous density for smooth ticks.
     */
    private function evolveTrafficDensity(
        string $vehicleId,
        string $routeId,
        int $tickIndex,
        ?float $previousDensity,
    ): float {
        $seed = $this->seed($vehicleId, $routeId);
        $phase = ($seed % 1000) / 1000.0 * M_PI * 2.0;

        // Slow + medium harmonics — route/vehicle specific, not per-tick random.
        $raw = 0.45
            + 0.30 * sin(($tickIndex / 9.0) + $phase)
            + 0.15 * sin(($tickIndex / 3.5) + ($seed % 11) * 0.37)
            + 0.08 * sin(($tickIndex / 1.7) + ($seed % 5) * 0.91);

        // Mild route bias (busier corridors)
        $routeBias = (($seed >> 8) % 100) / 500.0; // 0..0.2
        $raw = max(0.05, min(0.95, $raw + $routeBias));

        if ($previousDensity === null) {
            return $raw;
        }

        $delta = $raw - $previousDensity;

        return max(0.05, min(0.95, $previousDensity + max(-self::TRAFFIC_MAX_STEP, min(self::TRAFFIC_MAX_STEP, $delta))));
    }

    private function trafficFactorFromDensity(float $density): float
    {
        // density 0.05 → ~0.97, density 0.95 → ~0.38
        return max(0.25, min(1.0, 1.0 - ($density * 0.65)));
    }

    private function resolveRoadSpeedLimit(string $routeId, string $vehicleId): float
    {
        $limits = [80.0, 90.0, 100.0, 110.0, 120.0];
        $idx = $this->seed($vehicleId, $routeId) % count($limits);

        return $limits[$idx];
    }

    /**
     * Events stick for several ticks (block index) so they don't flicker.
     */
    private function resolveRoadEvent(
        string $vehicleId,
        string $routeId,
        int $tickIndex,
        float $trafficDensity,
    ): string {
        $block = intdiv(max(0, $tickIndex), 5);
        $roll = $this->seed($vehicleId, $routeId . ':evt:' . $block) % 100;

        if ($roll < 2) {
            return 'road_closure';
        }
        if ($roll < 5) {
            return 'accident';
        }
        if ($roll < 10) {
            return 'construction';
        }
        if ($roll < 14) {
            return 'border_crossing';
        }
        if ($roll < 18) {
            return 'rest_area';
        }
        if ($roll < 22) {
            return 'toll_gate';
        }
        if ($roll < 26) {
            return 'school_zone';
        }
        if ($trafficDensity > 0.72 || $roll < 40) {
            return $trafficDensity > 0.72 ? 'heavy_traffic' : 'light_traffic';
        }
        if ($roll < 55) {
            return 'light_traffic';
        }

        return 'none';
    }

    /**
     * @return array{
     *     construction_zone: bool,
     *     accident_nearby: bool,
     *     road_closed: bool,
     *     school_zone: bool,
     *     toll_gate_ahead: bool,
     *     border_crossing_ahead: bool
     * }
     */
    private function eventFlags(string $event): array
    {
        return [
            'construction_zone' => $event === 'construction',
            'accident_nearby' => $event === 'accident',
            'road_closed' => $event === 'road_closure',
            'school_zone' => $event === 'school_zone',
            'toll_gate_ahead' => $event === 'toll_gate',
            'border_crossing_ahead' => $event === 'border_crossing',
        ];
    }

    private function resolveWeather(string $vehicleId, int $tickIndex): string
    {
        // Weather changes slowly (per 12-tick block).
        $block = intdiv(max(0, $tickIndex), 12);
        $roll = $this->seed($vehicleId, 'wx:' . $block) % 100;

        return match (true) {
            $roll < 8 => 'snow',
            $roll < 22 => 'rain',
            $roll < 30 => 'fog',
            default => 'clear',
        };
    }

    private function resolveRoadCondition(string $weather): string
    {
        return match ($weather) {
            'snow' => 'icy',
            'rain', 'fog' => 'wet',
            default => 'dry',
        };
    }

    private function seed(string $vehicleId, string $salt): int
    {
        return abs(crc32($vehicleId . '|' . $salt));
    }
}
