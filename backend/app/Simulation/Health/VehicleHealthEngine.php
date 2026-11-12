<?php

namespace App\Simulation\Health;

use App\Simulation\Driver\DriverProfile;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * VehicleHealthEngine
 *
 * Simulates fuel, temperature, wear, and operational constraints.
 * No GPS, routing, or database persistence.
 *
 * Uses previous-tick motion (speed/accel) because Health runs before Dynamics.
 */
class VehicleHealthEngine
{
    public const CACHE_PREFIX = 'simulation:vehicle_health:';

    private const CACHE_TTL_SECONDS = 86400;

    /** Same key VehicleDynamicsEngine uses for end-of-tick speed. */
    private const SPEED_CACHE_PREFIX = 'simulation:vehicle_speed:';

    /**
     * @param 'moving'|'idle'|'stopped'|'offline' $vehicleState
     */
    public function compute(
        string $vehicleId,
        VehicleProfile $vehicleProfile,
        DriverProfile $driverProfile,
        string $vehicleState,
        float $trafficDensity = 0.3,
        float $roadSpeedLimitKmh = 90.0,
        float $tickSeconds = 60.0,
        ?string $timestamp = null,
    ): VehicleHealthSnapshot {
        $timestamp ??= now()->toIso8601String();
        $previous = $this->load($vehicleId);
        $speed = $this->loadPreviousSpeed($vehicleId);
        $accel = $previous?->lastAccelerationMs2 ?? 0.0;

        $capacity = $this->tankCapacityLiters($vehicleProfile);
        $fuelLevel = $previous?->fuelLevel ?? $this->initialFuelPercent($vehicleId);
        $engineTemp = $previous?->engineTemperature ?? 85.0;
        $engineHealth = $previous?->engineHealth ?? 100.0;
        $battery = $previous?->batteryLevel ?? 92.0;
        $brakeWear = $previous?->brakeWear ?? 4.0;
        $tyreWear = $previous?->tyreWear ?? 4.0;
        $maintenance = $previous?->maintenanceScore ?? 92.0;

        $consumptionRate = $this->fuelConsumptionRateLPer100km(
            $vehicleProfile,
            $driverProfile,
            $speed,
            $accel,
            $trafficDensity,
            $vehicleState
        );

        $distanceKm = max(0.0, ($speed / 3.6) * $tickSeconds) / 1000.0;
        if ($vehicleState === 'moving' && $distanceKm > 0) {
            $litersUsed = ($consumptionRate / 100.0) * $distanceKm;
            $fuelLevel = max(0.0, $fuelLevel - ($litersUsed / $capacity) * 100.0);
        } elseif ($vehicleState === 'idle') {
            $idleLph = match ($vehicleProfile->freightClass ?? VehicleProfile::FREIGHT_HEAVY_TRUCK) {
                VehicleProfile::FREIGHT_VAN => 0.8,
                VehicleProfile::FREIGHT_LIGHT_TRUCK => 1.1,
                default => 1.5,
            };
            $litersUsed = $idleLph * ($tickSeconds / 3600.0);
            $fuelLevel = max(0.0, $fuelLevel - ($litersUsed / $capacity) * 100.0);
            $consumptionRate = $idleLph; // L/h while idle
        }

        $engineTemp = $this->evolveTemperature(
            $engineTemp,
            $vehicleState,
            $accel,
            $trafficDensity,
            $tickSeconds
        );

        $wearStep = $distanceKm * 0.02;
        if ($accel < -0.01) {
            $brakeWear = min(100.0, $brakeWear + abs($accel) * 0.8 + $wearStep * 0.5);
        }
        if ($vehicleState === 'moving') {
            $tyreWear = min(100.0, $tyreWear + $wearStep + ($speed / 200.0) * 0.05);
            $engineHealth = max(0.0, $engineHealth - 0.002 - ($trafficDensity * 0.001) - (max(0.0, $accel) * 0.01));
            $maintenance = max(0.0, $maintenance - 0.0015);
            $battery = min(100.0, $battery + 0.02); // alternator
        } elseif ($vehicleState === 'idle') {
            $engineHealth = max(0.0, $engineHealth - 0.0005);
            $battery = max(0.0, $battery - 0.01);
        } elseif ($vehicleState === 'offline') {
            $battery = max(0.0, $battery - 0.005);
        }

        if ($engineTemp > 105.0) {
            $engineHealth = max(0.0, $engineHealth - 0.05);
        }

        $operationalStatus = $this->resolveOperationalStatus($fuelLevel, $engineHealth, $engineTemp, $battery);
        [$recommendedMaxSpeed, $accelerationScale] = $this->resolveDynamicsConstraints(
            $engineHealth,
            $fuelLevel,
            $engineTemp,
            $operationalStatus,
            $roadSpeedLimitKmh,
            $vehicleProfile
        );

        // Estimate accel for next tick's fuel model (toward typical load).
        $estimatedAccel = match ($vehicleState) {
            'moving' => max(-0.15, min(0.10, $accel * 0.7 + ($trafficDensity - 0.4) * 0.02)),
            'idle', 'stopped' => $accel * 0.5,
            default => 0.0,
        };

        $snapshot = new VehicleHealthSnapshot(
            vehicleId: $vehicleId,
            fuelLevel: round($fuelLevel, 2),
            fuelConsumptionRate: round($consumptionRate, 2),
            engineTemperature: round($engineTemp, 2),
            engineHealth: round($engineHealth, 2),
            batteryLevel: round($battery, 2),
            brakeWear: round($brakeWear, 2),
            tyreWear: round($tyreWear, 2),
            maintenanceScore: round($maintenance, 2),
            operationalStatus: $operationalStatus,
            recommendedMaxSpeed: round($recommendedMaxSpeed, 2),
            accelerationScale: round($accelerationScale, 3),
            lastAccelerationMs2: $estimatedAccel,
            timestamp: $timestamp,
        );

        $this->store($snapshot);

        Log::info('Vehicle health', [
            'vehicle_id' => $vehicleId,
            'fuel_level' => $snapshot->fuelLevel,
            'fuel_consumption_rate' => $snapshot->fuelConsumptionRate,
            'engine_temperature' => $snapshot->engineTemperature,
            'engine_health' => $snapshot->engineHealth,
            'operational_status' => $snapshot->operationalStatus,
            'recommended_max_speed' => $snapshot->recommendedMaxSpeed,
        ]);

        return $snapshot;
    }

    public function load(string $vehicleId): ?VehicleHealthSnapshot
    {
        $data = Cache::get(self::CACHE_PREFIX . $vehicleId);
        if (!is_array($data)) {
            return null;
        }

        return VehicleHealthSnapshot::fromArray($data);
    }

    public function store(VehicleHealthSnapshot $snapshot): void
    {
        Cache::put(
            self::CACHE_PREFIX . $snapshot->vehicleId,
            $snapshot->toArray(),
            self::CACHE_TTL_SECONDS
        );
    }

    /**
     * Persist post-dynamics acceleration for the next fuel tick.
     */
    public function rememberAcceleration(string $vehicleId, float $accelerationMs2): void
    {
        $previous = $this->load($vehicleId);
        if ($previous === null) {
            return;
        }

        $this->store(VehicleHealthSnapshot::fromArray([
            ...$previous->toArray(),
            'last_acceleration_ms2' => $accelerationMs2,
        ]));
    }

    private function loadPreviousSpeed(string $vehicleId): float
    {
        $value = Cache::get(self::SPEED_CACHE_PREFIX . $vehicleId);

        return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
    }

    private function tankCapacityLiters(VehicleProfile $profile): float
    {
        return match ($profile->freightClass ?? VehicleProfile::FREIGHT_HEAVY_TRUCK) {
            VehicleProfile::FREIGHT_VAN => 70.0,
            VehicleProfile::FREIGHT_LIGHT_TRUCK => 120.0,
            default => 400.0,
        };
    }

    private function initialFuelPercent(string $vehicleId): float
    {
        $seed = abs(crc32($vehicleId . '|fuel'));

        return 55.0 + ($seed % 40); // 55–94%
    }

    private function fuelConsumptionRateLPer100km(
        VehicleProfile $profile,
        DriverProfile $driver,
        float $speedKmh,
        float $accelMs2,
        float $trafficDensity,
        string $vehicleState,
    ): float {
        $base = match ($profile->freightClass ?? VehicleProfile::FREIGHT_HEAVY_TRUCK) {
            VehicleProfile::FREIGHT_VAN => 9.0,
            VehicleProfile::FREIGHT_LIGHT_TRUCK => 16.0,
            default => 32.0,
        };

        if ($vehicleState !== 'moving' || $speedKmh < 1.0) {
            return $base * 0.35;
        }

        $speedFactor = 1.0 + max(0.0, ($speedKmh - 60.0) / 120.0);
        $accelFactor = 1.0 + max(0.0, $accelMs2) * 4.0;
        $trafficFactor = 1.0 + $trafficDensity * 0.35;
        $driverFactor = match ($driver) {
            DriverProfile::Eco => 0.88,
            DriverProfile::Defensive => 0.92,
            DriverProfile::Normal => 1.0,
            DriverProfile::Aggressive => 1.18,
        };

        return max(2.0, $base * $speedFactor * $accelFactor * $trafficFactor * $driverFactor);
    }

    private function evolveTemperature(
        float $current,
        string $vehicleState,
        float $accelMs2,
        float $trafficDensity,
        float $tickSeconds,
    ): float {
        $target = match ($vehicleState) {
            'moving' => 88.0 + max(0.0, $accelMs2) * 40.0 + $trafficDensity * 12.0,
            'idle' => 82.0,
            'stopped' => 78.0,
            default => 70.0,
        };

        $maxStep = 2.5 * ($tickSeconds / 60.0); // smooth °C per tick
        $delta = max(-$maxStep, min($maxStep, $target - $current));

        return max(60.0, min(120.0, $current + $delta));
    }

    private function resolveOperationalStatus(
        float $fuelLevel,
        float $engineHealth,
        float $engineTemp,
        float $battery,
    ): string {
        if ($fuelLevel <= 0.5 || $engineHealth < 15.0 || $battery < 5.0) {
            return 'unavailable';
        }
        if ($engineHealth < 40.0 || $engineTemp > 110.0 || $fuelLevel < 8.0) {
            return 'critical';
        }
        if ($engineHealth < 70.0 || $engineTemp > 100.0 || $fuelLevel < 20.0) {
            return 'degraded';
        }

        return 'available';
    }

    /**
     * @return array{0: float, 1: float} [recommendedMaxSpeed, accelerationScale]
     */
    private function resolveDynamicsConstraints(
        float $engineHealth,
        float $fuelLevel,
        float $engineTemp,
        string $operationalStatus,
        float $roadSpeedLimitKmh,
        VehicleProfile $profile,
    ): array {
        $profileMax = $profile->maxSpeed !== null && $profile->maxSpeed > 0
            ? (float) $profile->maxSpeed
            : $roadSpeedLimitKmh;

        $maxSpeed = min($profileMax, $roadSpeedLimitKmh > 0 ? $roadSpeedLimitKmh : $profileMax);
        $accelScale = 1.0;

        if ($engineHealth < 70.0) {
            $healthFactor = max(0.35, $engineHealth / 100.0);
            $maxSpeed *= (0.55 + 0.45 * $healthFactor);
            $accelScale *= (0.50 + 0.50 * $healthFactor);
        }

        if ($engineTemp > 105.0) {
            $maxSpeed *= 0.85;
            $accelScale *= 0.75;
        }

        if ($fuelLevel < 10.0) {
            $maxSpeed *= 0.80;
            $accelScale *= 0.85;
        }

        if ($operationalStatus === 'unavailable') {
            $maxSpeed = 0.0;
            $accelScale = 0.0;
        } elseif ($operationalStatus === 'critical') {
            $maxSpeed = min($maxSpeed, 40.0);
            $accelScale = min($accelScale, 0.45);
        }

        return [max(0.0, $maxSpeed), max(0.0, min(1.0, $accelScale))];
    }
}
