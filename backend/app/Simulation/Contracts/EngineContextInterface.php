<?php

namespace App\Simulation\Contracts;

use App\Simulation\Stores\VehicleStateStore;
use App\Simulation\Stores\ShipmentStore;
use App\Simulation\Stores\DriverStore;
use App\Simulation\Stores\TelemetryStore;
use App\Simulation\Stores\EnvironmentStore;
use App\Simulation\Stores\StatisticsStore;

/**
 * Shared Engine Context for runtime state.
 * Modules access stores through this context.
 */
interface EngineContextInterface
{
    /**
     * Get current simulation time.
     */
    public function getSimulationTime(): \DateTime;

    /**
     * Set current simulation time.
     */
    public function setSimulationTime(\DateTime $time): void;

    /**
     * Get engine running status.
     */
    public function isRunning(): bool;

    /**
     * Set engine running status.
     */
    public function setRunning(bool $running): void;

    /**
     * Get current tick count.
     */
    public function getTickCount(): int;

    /**
     * Increment tick count.
     */
    public function incrementTickCount(): void;

    /**
     * Get configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed;

    /**
     * Set configuration value.
     */
    public function setConfig(string $key, mixed $value): void;

    /**
     * Get Vehicle State Store.
     */
    public function getVehicleStore(): VehicleStateStore;

    /**
     * Get Shipment Store.
     */
    public function getShipmentStore(): ShipmentStore;

    /**
     * Get Driver Store.
     */
    public function getDriverStore(): DriverStore;

    /**
     * Get Telemetry Store.
     */
    public function getTelemetryStore(): TelemetryStore;

    /**
     * Get Environment Store.
     */
    public function getEnvironmentStore(): EnvironmentStore;

    /**
     * Get Statistics Store.
     */
    public function getStatisticsStore(): StatisticsStore;

    /**
     * Get value from shared registry.
     */
    public function getRegistry(string $key, mixed $default = null): mixed;

    /**
     * Set value in shared registry.
     */
    public function setRegistry(string $key, mixed $value): void;

    /**
     * Check if registry key exists.
     */
    public function hasRegistry(string $key): bool;

    /**
     * Remove from registry.
     */
    public function removeRegistry(string $key): void;

    /**
     * Clear all registry data.
     */
    public function clearRegistry(): void;
}
