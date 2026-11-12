<?php

namespace App\Simulation\Dynamics;

/**
 * BrakingModel
 *
 * Smooth deceleration toward target speed — never an instant drop to zero.
 */
class BrakingModel
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
        $vTarget = max(0.0, min($targetSpeedKmh, $constraints->maxSpeedKmh) / 3.6);

        if ($vTarget >= $v0) {
            return [
                'acceleration_ms2' => 0.0,
                'current_speed_kmh' => max(0.0, $previousSpeedKmh),
            ];
        }

        $needed = ($v0 - $vTarget) / $tickSeconds;
        $decel = min($constraints->maxBrakingMs2, max(0.0, $needed));
        $v1 = max($vTarget, $v0 - $decel * $tickSeconds);

        return [
            'acceleration_ms2' => -$decel,
            'current_speed_kmh' => max(0.0, $v1 * 3.6),
        ];
    }

    /**
     * Minimum distance (m) to stop from current speed at max braking.
     */
    public function stoppingDistanceMeters(float $speedKmh, VehicleConstraints $constraints): float
    {
        $v = max(0.0, $speedKmh) / 3.6;
        $a = max(0.1, $constraints->maxBrakingMs2);

        return ($v * $v) / (2.0 * $a);
    }
}
