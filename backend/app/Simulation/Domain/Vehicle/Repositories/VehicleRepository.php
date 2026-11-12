<?php

namespace App\Simulation\Domain\Vehicle\Repositories;

use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Domain\Vehicle\Stores\VehicleStore;
use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;

/**
 * VehicleRepository - Runtime Access Layer.
 * Provides runtime access to vehicle data through the VehicleStore.
 */
class VehicleRepository
{
    public function __construct(
        private VehicleStore $store,
    ) {}

    /**
     * Register a vehicle with its state and runtime.
     */
    public function register(Vehicle $vehicle, ?VehicleState $state = null, ?VehicleRuntime $runtime = null): void
    {
        $this->store->registerVehicle($vehicle, $state, $runtime);
    }

    /**
     * Remove a vehicle and all associated data.
     */
    public function remove(string $vehicleId): void
    {
        $this->store->removeVehicle($vehicleId);
    }

    /**
     * Get vehicle static information.
     */
    public function getVehicle(string $vehicleId): ?Vehicle
    {
        return $this->store->getVehicle($vehicleId);
    }

    /**
     * Get vehicle dynamic state.
     */
    public function getVehicleState(string $vehicleId): ?VehicleState
    {
        return $this->store->getVehicleState($vehicleId);
    }

    /**
     * Get vehicle runtime information.
     */
    public function getVehicleRuntime(string $vehicleId): ?VehicleRuntime
    {
        return $this->store->getVehicleRuntime($vehicleId);
    }

    /**
     * Update vehicle state.
     */
    public function updateVehicleState(string $vehicleId, VehicleState $state): void
    {
        $this->store->updateVehicleState($vehicleId, $state);
    }

    /**
     * Update vehicle runtime.
     */
    public function updateVehicleRuntime(string $vehicleId, VehicleRuntime $runtime): void
    {
        $this->store->updateVehicleRuntime($vehicleId, $runtime);
    }

    /**
     * Set vehicle simulation state.
     */
    public function setSimulationState(string $vehicleId, VehicleSimulationState $state): void
    {
        $this->store->setVehicleSimulationState($vehicleId, $state);
    }

    /**
     * Set vehicle driving strategy.
     */
    public function setDrivingStrategy(string $vehicleId, string $strategy): void
    {
        $this->store->setVehicleDrivingStrategy($vehicleId, $strategy);
    }

    /**
     * Get vehicle driving strategy.
     */
    public function getDrivingStrategy(string $vehicleId): ?string
    {
        return $this->store->getVehicleDrivingStrategy($vehicleId);
    }

    /**
     * Request vehicle state transition.
     */
    public function requestTransition(string $vehicleId, VehicleSimulationState $targetState, string $requestedBy): void
    {
        $this->store->requestVehicleTransition($vehicleId, $targetState, $requestedBy);
    }

    /**
     * Clear vehicle pending transition.
     */
    public function clearTransition(string $vehicleId): void
    {
        $this->store->clearVehicleTransition($vehicleId);
    }

    /**
     * Get vehicle simulation state.
     */
    public function getSimulationState(string $vehicleId): ?VehicleSimulationState
    {
        return $this->store->getVehicleSimulationState($vehicleId);
    }

    /**
     * Check if vehicle can transition to state.
     */
    public function canTransitionState(string $vehicleId, VehicleSimulationState $state): bool
    {
        return $this->store->canTransitionVehicleState($vehicleId, $state);
    }

    /**
     * Get vehicles by simulation state.
     */
    public function getBySimulationState(VehicleSimulationState $state): array
    {
        return $this->store->getVehiclesBySimulationState($state);
    }

    /**
     * Check if vehicle exists.
     */
    public function exists(string $vehicleId): bool
    {
        return $this->store->hasVehicle($vehicleId);
    }

    /**
     * Get all vehicles.
     */
    public function getAll(): array
    {
        return $this->store->getAllVehicles();
    }

    /**
     * Get all vehicle states.
     */
    public function getAllStates(): array
    {
        return $this->store->getAllStates();
    }

    /**
     * Get all vehicle runtimes.
     */
    public function getAllRuntimes(): array
    {
        return $this->store->getAllRuntimes();
    }

    /**
     * Get vehicle count.
     */
    public function count(): int
    {
        return $this->store->count();
    }

    /**
     * Get vehicles by status.
     */
    public function getByStatus(string $status): array
    {
        return $this->store->getVehiclesByStatus($status);
    }

    /**
     * Get vehicles by simulation status.
     */
    public function getBySimulationStatus(string $status): array
    {
        return $this->store->getVehiclesBySimulationStatus($status);
    }

    /**
     * Get vehicles by driver.
     */
    public function getByDriver(string $driverId): array
    {
        return $this->store->getVehiclesByDriver($driverId);
    }

    /**
     * Get vehicles by shipment.
     */
    public function getByShipment(string $shipmentId): array
    {
        return $this->store->getVehiclesByShipment($shipmentId);
    }

    /**
     * Get underlying store.
     */
    public function getStore(): VehicleStore
    {
        return $this->store;
    }
}
