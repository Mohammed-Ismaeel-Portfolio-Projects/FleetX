<?php

namespace App\Simulation\Routing\Application\Models;

use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\Models\VehicleProfile;

/**
 * RoutePlanningContext Model.
 * Contains all context information for route planning.
 */
readonly class RoutePlanningContext
{
    public function __construct(
        public ?string $tripId,
        public ?string $shipmentId,
        public VehicleProfile $vehicle,
        public ?string $driverId,
        public RoutePlanningOptions $planningOptions,
        public array $constraints, // key-value constraint pairs
        public array $environment, // environmental factors
    ) {}

    /**
     * Create context with trip.
     */
    public static function withTrip(
        string $tripId,
        VehicleProfile $vehicle,
        RoutePlanningOptions $options,
    ): self {
        return new self(
            tripId: $tripId,
            shipmentId: null,
            vehicle: $vehicle,
            driverId: null,
            planningOptions: $options,
            constraints: [],
            environment: [],
        );
    }

    /**
     * Create context with shipment.
     */
    public static function withShipment(
        string $shipmentId,
        VehicleProfile $vehicle,
        RoutePlanningOptions $options,
    ): self {
        return new self(
            tripId: null,
            shipmentId: $shipmentId,
            vehicle: $vehicle,
            driverId: null,
            planningOptions: $options,
            constraints: [],
            environment: [],
        );
    }

    /**
     * Add constraint.
     */
    public function withConstraint(string $key, $value): self
    {
        $constraints = $this->constraints;
        $constraints[$key] = $value;
        
        return new self(
            tripId: $this->tripId,
            shipmentId: $this->shipmentId,
            vehicle: $this->vehicle,
            driverId: $this->driverId,
            planningOptions: $this->planningOptions,
            constraints: $constraints,
            environment: $this->environment,
        );
    }

    /**
     * Add environmental factor.
     */
    public function withEnvironment(string $key, $value): self
    {
        $environment = $this->environment;
        $environment[$key] = $value;
        
        return new self(
            tripId: $this->tripId,
            shipmentId: $this->shipmentId,
            vehicle: $this->vehicle,
            driverId: $this->driverId,
            planningOptions: $this->planningOptions,
            constraints: $this->constraints,
            environment: $environment,
        );
    }

    /**
     * Get constraint value.
     */
    public function getConstraint(string $key, $default = null)
    {
        return $this->constraints[$key] ?? $default;
    }

    /**
     * Get environmental factor.
     */
    public function getEnvironment(string $key, $default = null)
    {
        return $this->environment[$key] ?? $default;
    }

    /**
     * Check if has trip.
     */
    public function hasTrip(): bool
    {
        return $this->tripId !== null;
    }

    /**
     * Check if has shipment.
     */
    public function hasShipment(): bool
    {
        return $this->shipmentId !== null;
    }

    /**
     * Check if has driver.
     */
    public function hasDriver(): bool
    {
        return $this->driverId !== null;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'trip_id' => $this->tripId,
            'shipment_id' => $this->shipmentId,
            'vehicle' => $this->vehicle->toArray(),
            'driver_id' => $this->driverId,
            'planning_options' => $this->planningOptions->toArray(),
            'constraints' => $this->constraints,
            'environment' => $this->environment,
        ];
    }
}
