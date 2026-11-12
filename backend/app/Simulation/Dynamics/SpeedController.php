<?php

namespace App\Simulation\Dynamics;

/**
 * SpeedController
 *
 * Target speed → acceleration/braking → current speed for one tick.
 */
class SpeedController
{
    public function __construct(
        private AccelerationModel $accelerationModel,
        private BrakingModel $brakingModel,
    ) {}

    /**
     * @return array{acceleration_ms2: float, current_speed_kmh: float}
     */
    public function step(
        float $previousSpeedKmh,
        float $targetSpeedKmh,
        VehicleConstraints $constraints,
        float $tickSeconds,
    ): array {
        $previousSpeedKmh = max(0.0, $previousSpeedKmh);
        $targetSpeedKmh = max(0.0, min($targetSpeedKmh, $constraints->maxSpeedKmh));

        if ($targetSpeedKmh > $previousSpeedKmh + 0.05) {
            return $this->accelerationModel->apply(
                $previousSpeedKmh,
                $targetSpeedKmh,
                $constraints,
                $tickSeconds
            );
        }

        if ($targetSpeedKmh < $previousSpeedKmh - 0.05) {
            return $this->brakingModel->apply(
                $previousSpeedKmh,
                $targetSpeedKmh,
                $constraints,
                $tickSeconds
            );
        }

        return [
            'acceleration_ms2' => 0.0,
            'current_speed_kmh' => $previousSpeedKmh,
        ];
    }

    public function brakingModel(): BrakingModel
    {
        return $this->brakingModel;
    }
}
