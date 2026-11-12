<?php

namespace App\Simulation\Telemetry;

use App\Simulation\Dynamics\DynamicsTickResult;
use App\Simulation\Progress\RouteProgressState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FleetTelemetryEngine
 *
 * Assembles one immutable TelemetrySnapshot per vehicle per tick
 * from FSM, Dynamics, Route Progress, and prior runtime cache.
 *
 * No DB writes, routing, or GPS calculation.
 */
class FleetTelemetryEngine
{
    public const CACHE_PREFIX = 'simulation:telemetry:';

    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @param 'moving'|'idle'|'stopped'|'offline' $vehicleState
     * @param array{
     *     latitude: float,
     *     longitude: float,
     *     heading: float,
     *     from_fallback?: bool
     * }|null $trackingRuntime Fallback position when route progress is unavailable
     */
    public function build(
        string $vehicleId,
        string $shipmentId,
        string $vehicleState,
        ?DynamicsTickResult $dynamics,
        ?RouteProgressState $routeProgress,
        ?array $trackingRuntime = null,
        float $tickSeconds = 60.0,
        ?string $timestamp = null,
    ): TelemetrySnapshot {
        $previous = $this->load($vehicleId);
        $timestamp ??= now()->toIso8601String();

        $currentSpeed = $dynamics?->currentSpeedKmh ?? 0.0;
        $targetSpeed = $dynamics?->targetSpeedKmh ?? 0.0;
        $acceleration = $dynamics?->accelerationMs2 ?? 0.0;
        $tickDistance = $dynamics?->distanceMeters ?? 0.0;

        if ($routeProgress !== null) {
            $latitude = $routeProgress->currentLatitude;
            $longitude = $routeProgress->currentLongitude;
            $heading = $routeProgress->heading;
            $distanceTravelled = $routeProgress->distanceTravelledM;
            $remaining = $routeProgress->remainingDistanceM;
            $completion = $routeProgress->completionPercentage;
            $segment = $routeProgress->segmentIndex;
            $polylineIndex = $routeProgress->polylineIndex;
            $routeCompleted = $routeProgress->completed;
            $fromFallback = false;
        } else {
            $latitude = (float) ($trackingRuntime['latitude'] ?? $previous?->latitude ?? 0.0);
            $longitude = (float) ($trackingRuntime['longitude'] ?? $previous?->longitude ?? 0.0);
            $heading = (float) ($trackingRuntime['heading'] ?? $previous?->heading ?? 0.0);
            $distanceTravelled = ($previous?->distanceTravelledMeters ?? 0.0) + $tickDistance;
            $remaining = $previous?->remainingDistanceMeters ?? 0.0;
            $completion = $previous?->completionPercentage ?? 0.0;
            $segment = $previous?->currentSegment ?? 0;
            $polylineIndex = $previous?->polylineIndex ?? 0;
            $routeCompleted = (bool) ($trackingRuntime['arrived'] ?? $previous?->routeCompleted ?? false);
            $fromFallback = (bool) ($trackingRuntime['from_fallback'] ?? true);
        }

        if ($routeCompleted) {
            $currentSpeed = 0.0;
        }

        $drivingTime = $previous?->drivingTimeSeconds ?? 0.0;
        $idleTime = $previous?->idleTimeSeconds ?? 0.0;
        $engineRuntime = $previous?->engineRuntimeSeconds ?? 0.0;
        $odometer = ($previous?->odometerMeters ?? 0.0) + max(0.0, $tickDistance);

        if ($vehicleState === 'moving' && !$routeCompleted) {
            $drivingTime += $tickSeconds;
            $engineRuntime += $tickSeconds;
        } elseif ($vehicleState === 'idle') {
            $idleTime += $tickSeconds;
            $engineRuntime += $tickSeconds;
        } elseif ($vehicleState === 'stopped') {
            $idleTime += $tickSeconds;
            $engineRuntime += $tickSeconds;
        }

        $averageSpeed = $drivingTime > 0
            ? ($odometer / $drivingTime) * 3.6
            : 0.0;

        $gpsAccuracy = match ($vehicleState) {
            'moving' => 5.0,
            'idle' => 4.0,
            'stopped' => 7.0,
            default => 15.0,
        };

        $signalQuality = match ($vehicleState) {
            'moving', 'idle' => 'good',
            'stopped' => 'fair',
            default => 'poor',
        };

        $snapshot = new TelemetrySnapshot(
            vehicleId: $vehicleId,
            shipmentId: $shipmentId,
            timestamp: $timestamp,
            latitude: $latitude,
            longitude: $longitude,
            heading: $heading,
            currentSpeedKmh: $currentSpeed,
            targetSpeedKmh: $targetSpeed,
            acceleration: $acceleration,
            vehicleState: $vehicleState,
            distanceTravelledMeters: $distanceTravelled,
            remainingDistanceMeters: $remaining,
            completionPercentage: $completion,
            currentSegment: $segment,
            polylineIndex: $polylineIndex,
            drivingTimeSeconds: $drivingTime,
            idleTimeSeconds: $idleTime,
            engineRuntimeSeconds: $engineRuntime,
            averageSpeedKmh: round($averageSpeed, 2),
            odometerMeters: $odometer,
            gpsAccuracy: $gpsAccuracy,
            signalQuality: $signalQuality,
            fuelLevel: null,
            fuelConsumptionRate: null,
            engineTemperature: null,
            engineHealth: null,
            batteryLevel: null,
            fromFallbackCorridor: $fromFallback,
            routeCompleted: $routeCompleted,
        );

        $this->store($snapshot);

        Log::info('Telemetry generated', [
            'vehicle_id' => $vehicleId,
            'current_speed_kmh' => round($snapshot->currentSpeedKmh, 2),
            'vehicle_state' => $snapshot->vehicleState,
            'completion_percentage' => round($snapshot->completionPercentage, 2),
            'remaining_distance_m' => round($snapshot->remainingDistanceMeters, 2),
        ]);

        return $snapshot;
    }

    public function load(string $vehicleId): ?TelemetrySnapshot
    {
        $data = Cache::get(self::CACHE_PREFIX . $vehicleId);
        if (!is_array($data)) {
            return null;
        }

        return TelemetrySnapshot::fromArray($data);
    }

    public function store(TelemetrySnapshot $snapshot): void
    {
        Cache::put(
            self::CACHE_PREFIX . $snapshot->vehicleId,
            $snapshot->toArray(),
            self::CACHE_TTL_SECONDS
        );
    }
}
