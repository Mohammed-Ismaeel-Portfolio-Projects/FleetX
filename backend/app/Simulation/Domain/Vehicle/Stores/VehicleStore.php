<?php

namespace App\Simulation\Domain\Vehicle\Stores;

use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;

/**
 * VehicleStore - Manages Vehicle, VehicleState, and VehicleRuntime.
 * Provides unified access to all three vehicle models without mixing responsibilities.
 */
class VehicleStore
{
    private array $vehicles = [];
    private array $states = [];
    private array $runtimes = [];

    /**
     * Register a vehicle with its state and runtime.
     */
    public function registerVehicle(Vehicle $vehicle, ?VehicleState $state = null, ?VehicleRuntime $runtime = null): void
    {
        $this->vehicles[$vehicle->id] = $vehicle;
        
        if ($state === null) {
            $state = VehicleState::createInitial($vehicle->id);
        }
        $this->states[$vehicle->id] = $state;
        
        if ($runtime === null) {
            $runtime = VehicleRuntime::createInitial($vehicle->id);
        }
        $this->runtimes[$vehicle->id] = $runtime;
    }

    /**
     * Remove a vehicle and all associated data.
     */
    public function removeVehicle(string $vehicleId): void
    {
        unset($this->vehicles[$vehicleId]);
        unset($this->states[$vehicleId]);
        unset($this->runtimes[$vehicleId]);
    }

    /**
     * Get vehicle static information.
     */
    public function getVehicle(string $vehicleId): ?Vehicle
    {
        return $this->vehicles[$vehicleId] ?? null;
    }

    /**
     * Get vehicle dynamic state.
     */
    public function getVehicleState(string $vehicleId): ?VehicleState
    {
        return $this->states[$vehicleId] ?? null;
    }

    /**
     * Get vehicle runtime information.
     */
    public function getVehicleRuntime(string $vehicleId): ?VehicleRuntime
    {
        return $this->runtimes[$vehicleId] ?? null;
    }

    /**
     * Update vehicle state.
     */
    public function updateVehicleState(string $vehicleId, VehicleState $state): void
    {
        if (isset($this->vehicles[$vehicleId])) {
            $this->states[$vehicleId] = $state;
        }
    }

    /**
     * Update vehicle runtime.
     */
    public function updateVehicleRuntime(string $vehicleId, VehicleRuntime $runtime): void
    {
        if (isset($this->vehicles[$vehicleId])) {
            $this->runtimes[$vehicleId] = $runtime;
        }
    }

    /**
     * Set vehicle simulation state.
     */
    public function setVehicleSimulationState(string $vehicleId, VehicleSimulationState $state): void
    {
        if (isset($this->runtimes[$vehicleId])) {
            $this->runtimes[$vehicleId]->setState($state);
        }
    }

    /**
     * Set vehicle driving strategy.
     */
    public function setVehicleDrivingStrategy(string $vehicleId, string $strategy): void
    {
        if (isset($this->runtimes[$vehicleId])) {
            $this->runtimes[$vehicleId]->setDrivingStrategy($strategy);
        }
    }

    /**
     * Get vehicle driving strategy.
     */
    public function getVehicleDrivingStrategy(string $vehicleId): ?string
    {
        return $this->runtimes[$vehicleId]?->getDrivingStrategy();
    }

    /**
     * Request vehicle state transition.
     */
    public function requestVehicleTransition(string $vehicleId, VehicleSimulationState $targetState, string $requestedBy): void
    {
        if (isset($this->runtimes[$vehicleId])) {
            $this->runtimes[$vehicleId]->requestTransition($targetState, $requestedBy);
        }
    }

    /**
     * Clear vehicle pending transition.
     */
    public function clearVehicleTransition(string $vehicleId): void
    {
        if (isset($this->runtimes[$vehicleId])) {
            $this->runtimes[$vehicleId]->clearPendingTransition();
        }
    }

    /**
     * Get vehicle simulation state.
     */
    public function getVehicleSimulationState(string $vehicleId): ?VehicleSimulationState
    {
        return $this->runtimes[$vehicleId]?->getCurrentState();
    }

    /**
     * Check if vehicle can transition to state.
     */
    public function canTransitionVehicleState(string $vehicleId, VehicleSimulationState $state): bool
    {
        return $this->runtimes[$vehicleId]?->canTransitionTo($state) ?? false;
    }

    /**
     * Get vehicles by simulation state.
     */
    public function getVehiclesBySimulationState(VehicleSimulationState $state): array
    {
        return array_filter(
            $this->runtimes,
            fn($runtime) => $runtime->getCurrentState() === $state
        );
    }

    /**
     * Check if vehicle is registered.
     */
    public function hasVehicle(string $vehicleId): bool
    {
        return isset($this->vehicles[$vehicleId]);
    }

    /**
     * Get all registered vehicles.
     */
    public function getAllVehicles(): array
    {
        return $this->vehicles;
    }

    /**
     * Get all vehicle states.
     */
    public function getAllStates(): array
    {
        return $this->states;
    }

    /**
     * Get all vehicle runtimes.
     */
    public function getAllRuntimes(): array
    {
        return $this->runtimes;
    }

    /**
     * Get vehicle count.
     */
    public function count(): int
    {
        return count($this->vehicles);
    }

    /**
     * Get vehicles by status.
     */
    public function getVehiclesByStatus(string $status): array
    {
        return array_filter($this->states, fn($state) => $state->vehicleStatus === $status);
    }

    /**
     * Get vehicles by simulation status.
     */
    public function getVehiclesBySimulationStatus(string $status): array
    {
        return array_filter($this->runtimes, fn($runtime) => $runtime->simulationStatus === $status);
    }

    /**
     * Get vehicles by current driver.
     */
    public function getVehiclesByDriver(string $driverId): array
    {
        return array_filter($this->runtimes, fn($runtime) => $runtime->currentDriverId === $driverId);
    }

    /**
     * Get vehicles by current shipment.
     */
    public function getVehiclesByShipment(string $shipmentId): array
    {
        return array_filter($this->runtimes, fn($runtime) => $runtime->currentShipmentId === $shipmentId);
    }

    /**
     * Clear all vehicle data.
     */
    public function clear(): void
    {
        $this->vehicles = [];
        $this->states = [];
        $this->runtimes = [];
    }
}
