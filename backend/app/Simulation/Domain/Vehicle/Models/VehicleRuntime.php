<?php

namespace App\Simulation\Domain\Vehicle\Models;

use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;
use App\Simulation\Domain\Vehicle\StateMachines\StateTransitionMap;

/**
 * VehicleRuntime Model - Simulation Runtime State.
 * Represents simulation-specific runtime information for the vehicle.
 * Includes state machine for simulation state transitions.
 */
class VehicleRuntime
{
    private VehicleSimulationState $currentState;
    private ?VehicleSimulationState $previousState;
    private \DateTime $stateChangedAt;
    private \DateTime $enteredStateAt;
    private StateTransitionMap $transitionMap;
    
    // Transition request fields
    private ?VehicleSimulationState $pendingTransition = null;
    private ?\DateTime $requestedAt = null;
    private ?string $requestedBy = null;
    
    // Driving strategy
    private string $drivingStrategy = 'normal_driving';

    public function __construct(
        public string $vehicleId,
        public int $currentTick,
        public \DateTime $currentSimulationTime,
        public ?string $currentDriverId,
        public ?string $currentShipmentId,
        public ?string $currentRouteId,
        public string $simulationStatus, // paused, active, destroyed, spawned
        public string $currentMode, // manual, autonomous, idle
        public int $lastTickExecuted,
        VehicleSimulationState $currentState = VehicleSimulationState::OFF,
        ?VehicleSimulationState $previousState = null,
        ?\DateTime $stateChangedAt = null,
        ?\DateTime $enteredStateAt = null,
        ?VehicleSimulationState $pendingTransition = null,
        ?\DateTime $requestedAt = null,
        ?string $requestedBy = null,
        string $drivingStrategy = 'normal_driving',
    ) {
        $this->currentState = $currentState;
        $this->previousState = $previousState;
        $this->stateChangedAt = $stateChangedAt ?? new \DateTime();
        $this->enteredStateAt = $enteredStateAt ?? new \DateTime();
        $this->transitionMap = new StateTransitionMap();
        $this->pendingTransition = $pendingTransition;
        $this->requestedAt = $requestedAt;
        $this->requestedBy = $requestedBy;
        $this->drivingStrategy = $drivingStrategy;
    }

    /**
     * Create initial runtime for a vehicle.
     */
    public static function createInitial(string $vehicleId): self
    {
        $now = new \DateTime();
        return new self(
            vehicleId: $vehicleId,
            currentTick: 0,
            currentSimulationTime: $now,
            currentDriverId: null,
            currentShipmentId: null,
            currentRouteId: null,
            simulationStatus: 'spawned',
            currentMode: 'idle',
            lastTickExecuted: 0,
            currentState: VehicleSimulationState::OFF,
            previousState: null,
            stateChangedAt: $now,
            enteredStateAt: $now,
            pendingTransition: null,
            requestedAt: null,
            requestedBy: null,
            drivingStrategy: 'normal_driving',
        );
    }

    /**
     * Get driving strategy.
     */
    public function getDrivingStrategy(): string
    {
        return $this->drivingStrategy;
    }

    /**
     * Set driving strategy.
     */
    public function setDrivingStrategy(string $strategy): void
    {
        $this->drivingStrategy = $strategy;
    }

    /**
     * Get current simulation state.
     */
    public function getCurrentState(): VehicleSimulationState
    {
        return $this->currentState;
    }

    /**
     * Get previous simulation state.
     */
    public function getPreviousState(): ?VehicleSimulationState
    {
        return $this->previousState;
    }

    /**
     * Get state changed timestamp.
     */
    public function getStateChangedAt(): \DateTime
    {
        return $this->stateChangedAt;
    }

    /**
     * Get entered state at timestamp.
     */
    public function getEnteredStateAt(): \DateTime
    {
        return $this->enteredStateAt;
    }

    /**
     * Get time in current state.
     */
    public function getTimeInCurrentState(): \DateInterval
    {
        return $this->enteredStateAt->diff(new \DateTime());
    }

    /**
     * Get time in current state in milliseconds.
     */
    public function getTimeInCurrentStateMs(): int
    {
        $now = new \DateTime();
        $diff = $this->enteredStateAt->diff($now);
        return ($diff->h * 3600000) + ($diff->i * 60000) + ($diff->s * 1000) + ($diff->f * 1000000);
    }

    /**
     * Get pending transition.
     */
    public function getPendingTransition(): ?VehicleSimulationState
    {
        return $this->pendingTransition;
    }

    /**
     * Get requested at timestamp.
     */
    public function getRequestedAt(): ?\DateTime
    {
        return $this->requestedAt;
    }

    /**
     * Get requested by module/entity.
     */
    public function getRequestedBy(): ?string
    {
        return $this->requestedBy;
    }

    /**
     * Check if there is a pending transition.
     */
    public function hasPendingTransition(): bool
    {
        return $this->pendingTransition !== null;
    }

    /**
     * Request a state transition.
     */
    public function requestTransition(VehicleSimulationState $targetState, string $requestedBy): void
    {
        if (!$this->canTransitionTo($targetState)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->currentState->value} to {$targetState->value}"
            );
        }

        $this->pendingTransition = $targetState;
        $this->requestedAt = new \DateTime();
        $this->requestedBy = $requestedBy;
    }

    /**
     * Clear pending transition.
     */
    public function clearPendingTransition(): void
    {
        $this->pendingTransition = null;
        $this->requestedAt = null;
        $this->requestedBy = null;
    }

    /**
     * Restore persisted simulation state without transition validation.
     * Used only when hydrating from cache across artisan cycles.
     */
    public function restorePersistedState(
        VehicleSimulationState $state,
        ?VehicleSimulationState $previousState = null,
        ?\DateTime $enteredAt = null,
    ): void {
        $this->previousState = $previousState;
        $this->currentState = $state;
        $at = $enteredAt ?? new \DateTime();
        $this->stateChangedAt = clone $at;
        $this->enteredStateAt = clone $at;
        $this->clearPendingTransition();
    }

    /**
     * Set simulation state with transition validation.
     */
    public function setState(VehicleSimulationState $newState): void
    {
        if (!$this->canTransitionTo($newState)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->currentState->value} to {$newState->value}"
            );
        }

        $this->previousState = $this->currentState;
        $this->currentState = $newState;
        $this->stateChangedAt = new \DateTime();
        $this->enteredStateAt = new \DateTime();
    }

    /**
     * Execute pending transition.
     */
    public function executePendingTransition(): void
    {
        if ($this->pendingTransition === null) {
            throw new \RuntimeException('No pending transition to execute');
        }

        $targetState = $this->pendingTransition;
        $this->setState($targetState);
        $this->clearPendingTransition();
    }

    /**
     * Check if transition to given state is valid.
     */
    public function canTransitionTo(VehicleSimulationState $state): bool
    {
        return $this->transitionMap->canTransition($this->currentState, $state);
    }

    /**
     * Get allowed next states.
     */
    public function getAllowedTransitions(): array
    {
        return $this->transitionMap->getAllowedTransitions($this->currentState);
    }

    /**
     * Get state transition map.
     */
    public function getTransitionMap(): StateTransitionMap
    {
        return $this->transitionMap;
    }
}
