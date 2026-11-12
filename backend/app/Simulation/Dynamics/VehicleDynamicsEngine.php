<?php

namespace App\Simulation\Dynamics;

use App\Simulation\Routing\Application\Models\VehicleProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * VehicleDynamicsEngine
 *
 * Deterministic speed / acceleration / braking / tick distance.
 * No GPS, routing, or TrackingState persistence.
 */
class VehicleDynamicsEngine
{
    private const SPEED_CACHE_PREFIX = 'simulation:vehicle_speed:';
    private const CACHE_TTL_SECONDS = 86400;

    public function __construct(
        private SpeedController $speedController,
    ) {}

    /**
     * @param 'moving'|'idle'|'stopped'|'offline' $operationalState
     * @param float|null $remainingDistanceM Remaining route distance (for approach braking)
     * @param float|null $recommendedTargetSpeedKmh Desired target from Driver Intelligence
     * @param float|null $healthMaxSpeedKmh Optional Vehicle Health speed cap
     * @param float|null $healthAccelerationScale Optional Vehicle Health accel derate (0–1)
     */
    public function computeTick(
        string $vehicleId,
        string $operationalState,
        VehicleProfile $profile,
        float $tickSeconds = 60.0,
        ?float $remainingDistanceM = null,
        ?float $recommendedTargetSpeedKmh = null,
        ?float $healthMaxSpeedKmh = null,
        ?float $healthAccelerationScale = null,
    ): DynamicsTickResult {
        $constraints = VehicleConstraints::fromVehicleProfile($profile)
            ->withOperationalLimits($healthMaxSpeedKmh, $healthAccelerationScale);
        $previousSpeed = $this->loadSpeed($vehicleId);
        $targetSpeed = $this->resolveTargetSpeed(
            $operationalState,
            $constraints,
            $previousSpeed,
            $remainingDistanceM,
            $recommendedTargetSpeedKmh
        );

        $step = $this->speedController->step(
            $previousSpeed,
            $targetSpeed,
            $constraints,
            $tickSeconds
        );

        $currentSpeed = $step['current_speed_kmh'];
        $acceleration = $step['acceleration_ms2'];
        $distance = $this->distanceMeters($previousSpeed, $currentSpeed, $acceleration, $tickSeconds);

        $this->persistSpeed($vehicleId, $currentSpeed);

        $result = new DynamicsTickResult(
            vehicleId: $vehicleId,
            previousSpeedKmh: $previousSpeed,
            targetSpeedKmh: $targetSpeed,
            currentSpeedKmh: $currentSpeed,
            accelerationMs2: $acceleration,
            distanceMeters: $distance,
            tickSeconds: $tickSeconds,
        );

        Log::info('Vehicle dynamics', [
            'vehicle_id' => $vehicleId,
            'current_speed_kmh' => round($currentSpeed, 2),
            'target_speed_kmh' => round($targetSpeed, 2),
            'acceleration_ms2' => round($acceleration, 3),
            'distance_this_tick_m' => round($distance, 2),
            'operational_state' => $operationalState,
            'category' => $constraints->category,
        ]);

        return $result;
    }

    private function resolveTargetSpeed(
        string $operationalState,
        VehicleConstraints $constraints,
        float $previousSpeedKmh,
        ?float $remainingDistanceM,
        ?float $recommendedTargetSpeedKmh = null,
    ): float {
        if ($operationalState !== 'moving') {
            return 0.0;
        }

        // Environment recommended speed replaces fixed cruise when provided.
        $cruise = $recommendedTargetSpeedKmh !== null
            ? max(0.0, $recommendedTargetSpeedKmh)
            : $constraints->cruiseSpeedKmh;

        $target = min($cruise, $constraints->maxSpeedKmh);

        if ($remainingDistanceM !== null && $remainingDistanceM >= 0) {
            $stopping = $this->speedController->brakingModel()
                ->stoppingDistanceMeters($previousSpeedKmh, $constraints);

            // Begin braking early enough for a smooth stop.
            if ($remainingDistanceM <= max($stopping * 1.25, 250.0)) {
                $target = 0.0;
            } elseif ($remainingDistanceM < 2000.0) {
                // Approach: taper toward a low crawl then stop.
                $target = min($target, max(25.0, $constraints->cruiseSpeedKmh * 0.4));
            }
        }

        return min($target, $constraints->maxSpeedKmh);
    }

    private function distanceMeters(
        float $previousSpeedKmh,
        float $currentSpeedKmh,
        float $accelerationMs2,
        float $tickSeconds,
    ): float {
        $v0 = $previousSpeedKmh / 3.6;
        $v1 = $currentSpeedKmh / 3.6;
        $dt = max(0.0, $tickSeconds);

        // Prefer kinematic integral; fall back to trapezoid average.
        if (abs($accelerationMs2) > 1e-6) {
            $distance = ($v0 * $dt) + (0.5 * $accelerationMs2 * $dt * $dt);
            // Clamp to non-negative and consistent with end speed.
            return max(0.0, $distance);
        }

        return max(0.0, (($v0 + $v1) / 2.0) * $dt);
    }

    private function loadSpeed(string $vehicleId): float
    {
        $value = Cache::get(self::SPEED_CACHE_PREFIX . $vehicleId);

        return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
    }

    private function persistSpeed(string $vehicleId, float $speedKmh): void
    {
        Cache::put(self::SPEED_CACHE_PREFIX . $vehicleId, $speedKmh, self::CACHE_TTL_SECONDS);
    }
}
