<?php

namespace App\Services\TrackingCycle;

use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Runtime\SimulationRuntime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

/**
 * TrackingCycleService
 *
 * Orchestrates one fleet tracking cycle through SimulationRuntime / SimulationEngine.
 * TrackingSimulatorService remains the operational GPS implementation via adapter.
 */
class TrackingCycleService
{
    public function __construct(
        private SimulationRuntime $simulationRuntime
    ) {}

    /**
     * Execute a complete tracking cycle
     *
     * @return TrackingCycleResult
     */
    public function executeCycle(): TrackingCycleResult
    {
        $cycleId = Uuid::uuid4()->toString();
        $startedAt = Carbon::now();
        $errorsCount = 0;

        try {
            // Load engine-enrolled assignments (metrics — simulator filters the same way)
            $activeAssignments = SimulationEligibleAssignments::query()
                ->with(['shipment', 'vehicle', 'driver'])
                ->get();

            $assignmentsProcessed = $activeAssignments->count();

            // Invoke SimulationEngine (one tick) with error handling
            $simulatorResult = $this->invokeSimulator($activeAssignments);

            $finishedAt = Carbon::now();

            return TrackingCycleResult::success(
                cycleId: $cycleId,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                assignmentsProcessed: $assignmentsProcessed,
                vehiclesMoved: $simulatorResult['vehicles_moved'],
                idleVehicles: $simulatorResult['idle_vehicles'],
                stoppedVehicles: $simulatorResult['stopped_vehicles'],
                offlineVehicles: $simulatorResult['offline_vehicles'],
                trackingLogsCreated: $simulatorResult['tracking_logs_created'],
                trackingStatesUpdated: $simulatorResult['tracking_logs_created'], // Each log updates a state
                errorsCount: $errorsCount
            );
        } catch (\Throwable $e) {
            $finishedAt = Carbon::now();
            $errorsCount++;

            Log::error("Tracking cycle failed: " . $e->getMessage(), [
                'cycle_id' => $cycleId,
                'exception' => $e,
            ]);

            return TrackingCycleResult::failure(
                cycleId: $cycleId,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                errorsCount: $errorsCount,
                errorMessage: $e->getMessage()
            );
        }
    }

    /**
     * Run SimulationEngine for exactly one tick via SimulationRuntime.
     *
     * @param \Illuminate\Database\Eloquent\Collection $assignments
     * @return array{vehicles_moved: int, idle_vehicles: int, stopped_vehicles: int, offline_vehicles: int, tracking_logs_created: int}
     */
    private function invokeSimulator($assignments): array
    {
        unset($assignments); // retained for signature compatibility; simulator loads active assignments itself

        try {
            return $this->simulationRuntime->runSingleCycle();
        } catch (\Throwable $e) {
            Log::error("Simulator invocation failed: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            // Return empty result on failure
            return [
                'vehicles_moved' => 0,
                'idle_vehicles' => 0,
                'stopped_vehicles' => 0,
                'offline_vehicles' => 0,
                'tracking_logs_created' => 0,
            ];
        }
    }
}
