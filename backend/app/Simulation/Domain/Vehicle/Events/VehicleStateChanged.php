<?php

namespace App\Simulation\Domain\Vehicle\Events;

use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;

/**
 * VehicleStateChanged Event.
 * Published when a vehicle successfully transitions to a new state.
 */
readonly class VehicleStateChanged
{
    public function __construct(
        public string $vehicleId,
        public VehicleSimulationState $previousState,
        public VehicleSimulationState $newState,
        public string $requestedBy,
        public \DateTime $transitionedAt,
        public ?string $requestedAt = null,
    ) {}

    /**
     * Create event from runtime data.
     */
    public static function fromRuntime(
        string $vehicleId,
        VehicleSimulationState $previousState,
        VehicleSimulationState $newState,
        string $requestedBy,
        ?\DateTime $requestedAt = null,
    ): self {
        return new self(
            vehicleId: $vehicleId,
            previousState: $previousState,
            newState: $newState,
            requestedBy: $requestedBy,
            transitionedAt: new \DateTime(),
            requestedAt: $requestedAt,
        );
    }

    /**
     * Convert to array for event bus payload.
     */
    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'previous_state' => $this->previousState->value,
            'new_state' => $this->newState->value,
            'requested_by' => $this->requestedBy,
            'transitioned_at' => $this->transitionedAt->format('Y-m-d H:i:s.u'),
            'requested_at' => $this->requestedAt?->format('Y-m-d H:i:s.u'),
        ];
    }
}
