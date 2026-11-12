<?php

namespace App\Simulation\Core;

use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Stores\VehicleStateStore;
use App\Simulation\Stores\ShipmentStore;
use App\Simulation\Stores\DriverStore;
use App\Simulation\Stores\TelemetryStore;
use App\Simulation\Stores\EnvironmentStore;
use App\Simulation\Stores\StatisticsStore;

/**
 * Implementation of shared Engine Context.
 * Provides access to dedicated runtime stores.
 */
class EngineContext implements EngineContextInterface
{
    private \DateTime $simulationTime;
    private bool $running = false;
    private int $tickCount = 0;
    private array $config = [];
    private array $registry = [];

    private VehicleStateStore $vehicleStore;
    private ShipmentStore $shipmentStore;
    private DriverStore $driverStore;
    private TelemetryStore $telemetryStore;
    private EnvironmentStore $environmentStore;
    private StatisticsStore $statisticsStore;

    public function __construct()
    {
        $this->simulationTime = new \DateTime();
        
        // Initialize stores
        $this->vehicleStore = new VehicleStateStore();
        $this->shipmentStore = new ShipmentStore();
        $this->driverStore = new DriverStore();
        $this->telemetryStore = new TelemetryStore();
        $this->environmentStore = new EnvironmentStore();
        $this->statisticsStore = new StatisticsStore();
    }

    public function getSimulationTime(): \DateTime
    {
        return clone $this->simulationTime;
    }

    public function setSimulationTime(\DateTime $time): void
    {
        $this->simulationTime = clone $time;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function setRunning(bool $running): void
    {
        $this->running = $running;
    }

    public function getTickCount(): int
    {
        return $this->tickCount;
    }

    public function incrementTickCount(): void
    {
        $this->tickCount++;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function getVehicleStore(): VehicleStateStore
    {
        return $this->vehicleStore;
    }

    public function getShipmentStore(): ShipmentStore
    {
        return $this->shipmentStore;
    }

    public function getDriverStore(): DriverStore
    {
        return $this->driverStore;
    }

    public function getTelemetryStore(): TelemetryStore
    {
        return $this->telemetryStore;
    }

    public function getEnvironmentStore(): EnvironmentStore
    {
        return $this->environmentStore;
    }

    public function getStatisticsStore(): StatisticsStore
    {
        return $this->statisticsStore;
    }

    public function getRegistry(string $key, mixed $default = null): mixed
    {
        return $this->registry[$key] ?? $default;
    }

    public function setRegistry(string $key, mixed $value): void
    {
        $this->registry[$key] = $value;
    }

    public function hasRegistry(string $key): bool
    {
        return isset($this->registry[$key]);
    }

    public function removeRegistry(string $key): void
    {
        unset($this->registry[$key]);
    }

    public function clearRegistry(): void
    {
        $this->registry = [];
    }
}
