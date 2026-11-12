<?php

namespace App\Simulation\DTOs;

/**
 * DTO for simulation configuration.
 * Used to initialize the engine with settings.
 */
readonly class SimulationConfig
{
    public function __construct(
        public int $tickIntervalMs = 100,
        public float $timeScale = 1.0,
        public ?\DateTime $startTime = null,
        public array $moduleConfigs = [],
    ) {
        $this->startTime = $startTime ?? new \DateTime();
    }

    /**
     * Convert to array for engine context.
     */
    public function toArray(): array
    {
        return [
            'tick_interval_ms' => $this->tickIntervalMs,
            'time_scale' => $this->timeScale,
            'start_time' => $this->startTime->format('Y-m-d H:i:s'),
            'module_configs' => $this->moduleConfigs,
        ];
    }
}
