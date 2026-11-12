<?php

namespace App\Simulation\Domain\Fuel\Configuration;

/**
 * FuelConfiguration.
 * Configuration for fuel simulation.
 */
class FuelConfiguration
{
    public function __construct(
        private int $calculationIntervalMs = 1000, // Default 1 second
        private bool $enableLowFuelWarning = true,
        private float $lowFuelThreshold = 10.0, // 10%
        private array $strategyOptions = [],
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            calculationIntervalMs: $config['calculation_interval_ms'] ?? 1000,
            enableLowFuelWarning: $config['enable_low_fuel_warning'] ?? true,
            lowFuelThreshold: $config['low_fuel_threshold'] ?? 10.0,
            strategyOptions: $config['strategy_options'] ?? [],
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'calculation_interval_ms' => $this->calculationIntervalMs,
            'enable_low_fuel_warning' => $this->enableLowFuelWarning,
            'low_fuel_threshold' => $this->lowFuelThreshold,
            'strategy_options' => $this->strategyOptions,
        ];
    }

    /**
     * Get calculation interval in milliseconds.
     */
    public function getCalculationIntervalMs(): int
    {
        return $this->calculationIntervalMs;
    }

    /**
     * Set calculation interval in milliseconds.
     */
    public function setCalculationIntervalMs(int $intervalMs): void
    {
        $this->calculationIntervalMs = $intervalMs;
    }

    /**
     * Check if low fuel warning is enabled.
     */
    public function isLowFuelWarningEnabled(): bool
    {
        return $this->enableLowFuelWarning;
    }

    /**
     * Enable or disable low fuel warning.
     */
    public function setLowFuelWarningEnabled(bool $enabled): void
    {
        $this->enableLowFuelWarning = $enabled;
    }

    /**
     * Get low fuel threshold percentage.
     */
    public function getLowFuelThreshold(): float
    {
        return $this->lowFuelThreshold;
    }

    /**
     * Set low fuel threshold percentage.
     */
    public function setLowFuelThreshold(float $threshold): void
    {
        $this->lowFuelThreshold = $threshold;
    }

    /**
     * Get strategy options.
     */
    public function getStrategyOptions(): array
    {
        return $this->strategyOptions;
    }

    /**
     * Set strategy options.
     */
    public function setStrategyOptions(array $options): void
    {
        $this->strategyOptions = $options;
    }
}
