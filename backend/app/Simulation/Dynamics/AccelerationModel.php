<?php

namespace App\Simulation\Dynamics;

/**
 * AccelerationModel
 *
 * Smooth positive acceleration toward target speed, clamped by vehicle limits.
 */
class AccelerationModel
{
    /**
     * @return array{acceleration_ms2: float, current_speed_kmh: float}
     */
    public function apply(
        float $previousSpeedKmh,
        float $targetSpeedKmh,
        VehicleConstraints $constraints,
        float $tickSeconds,
    ): array {
        $tickSeconds = max(0.001, $tickSeconds);
        $v0 = $previousSpeedKmh / 3.6;
        $vTarget = min($targetSpeedKmh, $constraints->maxSpeedKmh) / 3.6;

        if ($vTarget <= $v0) {
            return [
                'acceleration_ms2' => 0.0,
                'current_speed_kmh' => max(0.0, $previousSpeedKmh),
            ];
        }

        $needed = ($vTarget - $v0) / $tickSeconds;
        $accel = min($constraints->maxAccelerationMs2, max(0.0, $needed));
        $v1 = min($vTarget, $v0 + $accel * $tickSeconds);

        return [
            'acceleration_ms2' => $accel,
            'current_speed_kmh' => max(0.0, $v1 * 3.6),
        ];
    }
}
