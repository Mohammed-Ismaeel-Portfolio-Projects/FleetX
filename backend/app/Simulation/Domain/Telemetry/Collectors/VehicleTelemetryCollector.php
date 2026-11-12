<?php

namespace App\Simulation\Domain\Telemetry\Collectors;

use App\Simulation\Domain\Telemetry\DTOs\TelemetrySnapshot;
use App\Simulation\Domain\Vehicle\Models\Vehicle;
use App\Simulation\Domain\Vehicle\Models\VehicleState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Core\SimulationClock;

/**
 * VehicleTelemetryCollector.
 * Collects telemetry data from vehicle models.
 * Reads only existing values, never generates new data.
 */
class VehicleTelemetryCollector
{
    public function __construct(
        private SimulationClock $clock,
    ) {}

    /**
     * Collect telemetry snapshot from vehicle data.
     */
    public function collect(
        Vehicle $vehicle,
        VehicleState $state,
        VehicleRuntime $runtime,
    ): TelemetrySnapshot {
        return new TelemetrySnapshot(
            vehicleId: $vehicle->id,
            simulationTime: $this->clock->getCurrentTime(),
            currentSpeed: $state->currentSpeed,
            rpm: $state->rpm,
            gear: $state->currentGear,
            throttlePosition: $state->throttlePosition,
            engineLoad: $state->engineLoad,
            fuelLevel: $state->fuelLevel,
            batteryVoltage: $state->batteryVoltage,
            vehicleStatus: $runtime->simulationStatus,
            simulationState: $runtime->getCurrentState(),
        );
    }
}
