<?php

namespace App\Simulation\Domain\Telemetry\Mappers;

use App\Simulation\Domain\Telemetry\DTOs\TelemetrySnapshot;
use App\Simulation\Domain\Telemetry\DTOs\TelemetrySignalCollection;
use App\Simulation\Domain\Telemetry\DTOs\TelemetrySignal;

/**
 * TelemetrySignalMapper.
 * Converts TelemetrySnapshot to TelemetrySignalCollection.
 * Maps snapshot fields to named signals with units and quality.
 */
class TelemetrySignalMapper
{
    /**
     * Map telemetry snapshot to signal collection.
     */
    public function map(TelemetrySnapshot $snapshot): TelemetrySignalCollection
    {
        $collection = new TelemetrySignalCollection(
            vehicleId: $snapshot->vehicleId,
            timestamp: $snapshot->simulationTime,
            signals: [],
        );

        // Map vehicle.speed
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'vehicle.speed',
            value: $snapshot->currentSpeed,
            unit: 'km/h',
            timestamp: $snapshot->simulationTime,
            quality: $this->assessQuality($snapshot->currentSpeed, 0, 500),
        ));

        // Map engine.rpm
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'engine.rpm',
            value: $snapshot->rpm,
            unit: 'rpm',
            timestamp: $snapshot->simulationTime,
            quality: $this->assessQuality($snapshot->rpm, 0, 10000),
        ));

        // Map transmission.gear
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'transmission.gear',
            value: $snapshot->gear,
            unit: 'gear',
            timestamp: $snapshot->simulationTime,
            quality: $this->assessQuality($snapshot->gear, -1, 10),
        ));

        // Map engine.throttle
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'engine.throttle',
            value: $snapshot->throttlePosition,
            unit: '%',
            timestamp: $snapshot->simulationTime,
            quality: $this->assessQuality($snapshot->throttlePosition, 0, 100),
        ));

        // Map engine.load
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'engine.load',
            value: $snapshot->engineLoad,
            unit: '%',
            timestamp: $snapshot->simulationTime,
            quality: $this->assessQuality($snapshot->engineLoad, 0, 100),
        ));

        // Map fuel.level
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'fuel.level',
            value: $snapshot->fuelLevel,
            unit: '%',
            timestamp: $snapshot->simulationTime,
            quality: $this->assessQuality($snapshot->fuelLevel, 0, 100),
        ));

        // Map battery.voltage
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'battery.voltage',
            value: $snapshot->batteryVoltage,
            unit: 'V',
            timestamp: $snapshot->simulationTime,
            quality: $this->assessQuality($snapshot->batteryVoltage, 10, 15),
        ));

        // Map vehicle.status
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'vehicle.status',
            value: match($snapshot->vehicleStatus) {
                'active' => 1,
                'inactive' => 0,
                default => -1,
            },
            unit: 'status',
            timestamp: $snapshot->simulationTime,
            quality: 'good',
        ));

        // Map vehicle.simulation_state
        $collection = $collection->addSignal(new TelemetrySignal(
            name: 'vehicle.simulation_state',
            value: match($snapshot->simulationState->value) {
                'off' => 0,
                'ignition_on' => 1,
                'starting' => 2,
                'idle' => 3,
                'moving' => 4,
                'stopping' => 5,
                'parked' => 6,
                'broken_down' => 7,
                'maintenance' => 8,
            },
            unit: 'state',
            timestamp: $snapshot->simulationTime,
            quality: 'good',
        ));

        return $collection;
    }

    /**
     * Assess signal quality based on value range.
     */
    private function assessQuality(float $value, float $min, float $max): string
    {
        if ($value < $min || $value > $max) {
            return 'bad';
        }
        if ($value < $min * 0.1 || $value > $max * 0.9) {
            return 'uncertain';
        }
        return 'good';
    }
}
