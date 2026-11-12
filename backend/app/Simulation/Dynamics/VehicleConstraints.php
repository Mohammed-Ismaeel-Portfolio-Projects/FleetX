<?php

namespace App\Simulation\Dynamics;

use App\Simulation\Routing\Application\Models\VehicleProfile;

/**
 * VehicleConstraints
 *
 * Derives physical limits from the vehicle routing profile / freight class.
 */
readonly class VehicleConstraints
{
    public function __construct(
        public float $maxSpeedKmh,
        public float $cruiseSpeedKmh,
        public float $maxAccelerationMs2,
        public float $maxBrakingMs2,
        public string $category,
    ) {}

    public static function fromVehicleProfile(VehicleProfile $profile): self
    {
        $category = $profile->freightClass ?? VehicleProfile::FREIGHT_HEAVY_TRUCK;

        // Acceleration / braking are calibrated for coarse simulation ticks (~60s):
        // ~0.06 m/s² ≈ 13 km/h per minute — smooth multi-tick ramps, no instant jumps.
        [$defaultMax, $cruise, $accel, $brake] = match ($category) {
            VehicleProfile::FREIGHT_VAN => [120.0, 100.0, 0.10, 0.22],
            VehicleProfile::FREIGHT_LIGHT_TRUCK => [100.0, 85.0, 0.08, 0.18],
            default => [90.0, 80.0, 0.06, 0.15], // heavy_truck
        };

        $maxSpeed = $profile->maxSpeed !== null && $profile->maxSpeed > 0
            ? min((float) $profile->maxSpeed, $defaultMax)
            : $defaultMax;

        $cruiseSpeed = min($cruise, $maxSpeed * 0.9);

        return new self(
            maxSpeedKmh: $maxSpeed,
            cruiseSpeedKmh: max(30.0, $cruiseSpeed),
            maxAccelerationMs2: $accel,
            maxBrakingMs2: $brake,
            category: $category,
        );
    }

    /**
     * Apply operational health derates without changing base profile derivation.
     */
    public function withOperationalLimits(?float $maxSpeedKmh, ?float $accelerationScale): self
    {
        $speed = $this->maxSpeedKmh;
        if ($maxSpeedKmh !== null) {
            $speed = min($speed, max(0.0, $maxSpeedKmh));
        }

        $accel = $this->maxAccelerationMs2;
        if ($accelerationScale !== null) {
            $accel = max(0.0, $accel * max(0.0, min(1.0, $accelerationScale)));
        }

        return new self(
            maxSpeedKmh: $speed,
            cruiseSpeedKmh: min($this->cruiseSpeedKmh, max(0.0, $speed)),
            maxAccelerationMs2: $accel,
            maxBrakingMs2: $this->maxBrakingMs2,
            category: $this->category,
        );
    }
}
