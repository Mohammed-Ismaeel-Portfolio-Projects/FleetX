<?php

namespace App\Simulation\Domain\Vehicle\Services;

use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * VehicleStateMachineService
 *
 * SimulationEngine-owned orchestrator for VehicleSimulationState transitions.
 * Reuses VehicleRuntime + StateTransitionMap. Persists FSM state across artisan cycles.
 */
class VehicleStateMachineService
{
    private const CACHE_PREFIX = 'simulation:vehicle_fsm:';
    private const CACHE_TTL_SECONDS = 86400;
    private const MAX_TRANSITIONS_PER_TICK = 8;

    public function __construct(
        private VehicleStateTransitionEvaluator $evaluator
    ) {}

    /**
     * Resolve FSM for a vehicle, applying validated transitions for this tick.
     *
     * @param array{
     *     has_active_assignment: bool,
     *     arrived_at_destination: bool,
     *     has_gps_fix: bool
     * } $context
     * @return array{
     *     vehicle_id: string,
     *     simulation_state: VehicleSimulationState,
     *     operational_state: 'moving'|'idle'|'stopped'|'offline',
     *     transitions: list<array{from: string, to: string, reason: string}>
     * }
     */
    public function resolveForTick(string $vehicleId, array $context): array
    {
        $runtime = $this->loadRuntime($vehicleId);
        $transitions = [];

        for ($i = 0; $i < self::MAX_TRANSITIONS_PER_TICK; $i++) {
            $desired = $this->evaluator->evaluateDesiredState($runtime, $context);
            if ($desired === null) {
                break;
            }

            $from = $runtime->getCurrentState();

            if (!$runtime->canTransitionTo($desired)) {
                Log::warning('Rejected vehicle state transition', [
                    'vehicle_id' => $vehicleId,
                    'from' => $from->value,
                    'to' => $desired->value,
                    'reason' => 'Illegal transition (StateTransitionMap)',
                ]);
                break;
            }

            $reason = $this->evaluator->reasonForTransition($from, $desired, $context);

            try {
                $runtime->setState($desired);
            } catch (\InvalidArgumentException $e) {
                Log::warning('Rejected vehicle state transition', [
                    'vehicle_id' => $vehicleId,
                    'from' => $from->value,
                    'to' => $desired->value,
                    'reason' => $e->getMessage(),
                ]);
                break;
            }

            $this->logTransition($vehicleId, $from, $desired, $reason);
            $transitions[] = [
                'from' => $from->value,
                'to' => $desired->value,
                'reason' => $reason,
            ];
        }

        $this->persistRuntime($runtime);

        $simulationState = $runtime->getCurrentState();

        return [
            'vehicle_id' => $vehicleId,
            'simulation_state' => $simulationState,
            'operational_state' => $this->toOperationalState($simulationState),
            'transitions' => $transitions,
        ];
    }

    /**
     * Map FSM state to TrackingSimulatorService operational telemetry mode.
     *
     * @return 'moving'|'idle'|'stopped'|'offline'
     */
    public function toOperationalState(VehicleSimulationState $state): string
    {
        return match ($state) {
            VehicleSimulationState::MOVING => 'moving',
            VehicleSimulationState::IDLE, VehicleSimulationState::PARKED => 'idle',
            VehicleSimulationState::STOPPING => 'stopped',
            VehicleSimulationState::OFF,
            VehicleSimulationState::IGNITION_ON,
            VehicleSimulationState::STARTING,
            VehicleSimulationState::BROKEN_DOWN,
            VehicleSimulationState::MAINTENANCE => 'offline',
        };
    }

    private function loadRuntime(string $vehicleId): VehicleRuntime
    {
        $payload = Cache::get(self::CACHE_PREFIX . $vehicleId);
        $runtime = VehicleRuntime::createInitial($vehicleId);

        if (!is_array($payload) || empty($payload['state'])) {
            return $runtime;
        }

        try {
            $state = VehicleSimulationState::from((string) $payload['state']);
        } catch (\ValueError) {
            return $runtime;
        }

        $previous = isset($payload['previous_state'])
            ? VehicleSimulationState::tryFrom((string) $payload['previous_state'])
            : null;

        $enteredAt = null;
        if (!empty($payload['entered_at'])) {
            try {
                $enteredAt = new \DateTime((string) $payload['entered_at']);
            } catch (\Exception) {
                $enteredAt = null;
            }
        }

        $runtime->restorePersistedState($state, $previous, $enteredAt);

        return $runtime;
    }

    private function persistRuntime(VehicleRuntime $runtime): void
    {
        Cache::put(self::CACHE_PREFIX . $runtime->vehicleId, [
            'state' => $runtime->getCurrentState()->value,
            'previous_state' => $runtime->getPreviousState()?->value,
            'entered_at' => $runtime->getEnteredStateAt()->format(\DateTime::ATOM),
        ], self::CACHE_TTL_SECONDS);
    }

    private function logTransition(
        string $vehicleId,
        VehicleSimulationState $from,
        VehicleSimulationState $to,
        string $reason
    ): void {
        Log::info(
            "Vehicle {$vehicleId}\n{$from->value}\n↓\n{$to->value}\nReason:\n{$reason}"
        );
    }
}
