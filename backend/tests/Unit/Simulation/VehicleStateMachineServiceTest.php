<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Domain\Vehicle\Enums\VehicleSimulationState;
use App\Simulation\Domain\Vehicle\Models\VehicleRuntime;
use App\Simulation\Domain\Vehicle\Services\VehicleStateMachineService;
use App\Simulation\Domain\Vehicle\Services\VehicleStateTransitionEvaluator;
use App\Simulation\Domain\Vehicle\StateMachines\StateTransitionMap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class VehicleStateMachineServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_bootstraps_off_to_moving_through_legal_transitions(): void
    {
        Log::spy();

        $service = new VehicleStateMachineService(new VehicleStateTransitionEvaluator());
        $result = $service->resolveForTick('vehicle-1', [
            'has_active_assignment' => true,
            'arrived_at_destination' => false,
            'has_gps_fix' => false,
        ]);

        $this->assertSame(VehicleSimulationState::MOVING, $result['simulation_state']);
        $this->assertSame('moving', $result['operational_state']);
        $this->assertGreaterThanOrEqual(4, count($result['transitions']));
        $this->assertSame('off', $result['transitions'][0]['from']);
        $this->assertSame('moving', $result['transitions'][count($result['transitions']) - 1]['to']);
    }

    public function test_rejects_illegal_transition_via_map(): void
    {
        $map = new StateTransitionMap();
        $this->assertFalse(
            $map->canTransition(VehicleSimulationState::IDLE, VehicleSimulationState::STOPPING)
        );
        $this->assertFalse(
            $map->canTransition(VehicleSimulationState::MOVING, VehicleSimulationState::IDLE)
        );
    }

    public function test_moving_stays_moving_when_not_arrived(): void
    {
        $service = new VehicleStateMachineService(new VehicleStateTransitionEvaluator());
        $service->resolveForTick('vehicle-2', [
            'has_active_assignment' => true,
            'arrived_at_destination' => false,
            'has_gps_fix' => true,
        ]);

        Log::spy();
        $second = $service->resolveForTick('vehicle-2', [
            'has_active_assignment' => true,
            'arrived_at_destination' => false,
            'has_gps_fix' => true,
        ]);

        $this->assertSame(VehicleSimulationState::MOVING, $second['simulation_state']);
        $this->assertSame([], $second['transitions']);
    }

    public function test_vehicle_runtime_set_state_rejects_illegal(): void
    {
        $runtime = VehicleRuntime::createInitial('v-x');
        // OFF can only go to IGNITION_ON
        $this->expectException(\InvalidArgumentException::class);
        $runtime->setState(VehicleSimulationState::MOVING);
    }
}
