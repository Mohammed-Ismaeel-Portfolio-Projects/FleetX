<?php

namespace App\Simulation\Domain\GPS\Models;

/**
 * GPSSignal Model.
 * Represents GPS signal quality metrics.
 */
readonly class GPSSignal
{
    public function __construct(
        public float $accuracy, // meters
        public float $hdop, // Horizontal Dilution of Precision
        public int $satelliteCount, // Number of satellites
        public string $signalQuality, // 'excellent', 'good', 'fair', 'poor'
        public string $fixType, // 'none', '2d', '3d'
    ) {}

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'accuracy' => $this->accuracy,
            'hdop' => $this->hdop,
            'satellite_count' => $this->satelliteCount,
            'signal_quality' => $this->signalQuality,
            'fix_type' => $this->fixType,
        ];
    }
}
