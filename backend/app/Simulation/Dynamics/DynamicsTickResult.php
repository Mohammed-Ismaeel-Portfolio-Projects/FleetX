<?php

namespace App\Simulation\Dynamics;

/**
 * Result of one dynamics tick (no GPS / routing).
 */
readonly class DynamicsTickResult
{
    public function __construct(
        public string $vehicleId,
        public float $previousSpeedKmh,
        public float $targetSpeedKmh,
        public float $currentSpeedKmh,
        public float $accelerationMs2,
        public float $distanceMeters,
        public float $tickSeconds,
    ) {}

    /**
     * Effective constant speed that yields the same distance over the tick
     * (for RouteProgressEngine.advance without modifying that engine).
     */
    public function effectiveSpeedKmhForDistance(): float
    {
        if ($this->tickSeconds <= 0) {
            return 0.0;
        }

        return ($this->distanceMeters / $this->tickSeconds) * 3.6;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'previous_speed_kmh' => $this->previousSpeedKmh,
            'target_speed_kmh' => $this->targetSpeedKmh,
            'current_speed_kmh' => $this->currentSpeedKmh,
            'acceleration_ms2' => $this->accelerationMs2,
            'distance_meters' => $this->distanceMeters,
            'tick_seconds' => $this->tickSeconds,
        ];
    }
}
