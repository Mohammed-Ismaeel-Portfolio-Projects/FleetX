<?php

namespace App\Services\TrackingCycle;

use Carbon\Carbon;

readonly class TrackingCycleResult
{
    public function __construct(
        public string $cycleId,
        public Carbon $startedAt,
        public Carbon $finishedAt,
        public int $durationMs,
        public int $assignmentsProcessed,
        public int $vehiclesMoved,
        public int $idleVehicles,
        public int $stoppedVehicles,
        public int $offlineVehicles,
        public int $trackingLogsCreated,
        public int $trackingStatesUpdated,
        public int $errorsCount,
        public string $status
    ) {}

    /**
     * Create a successful tracking cycle result
     */
    public static function success(
        string $cycleId,
        Carbon $startedAt,
        Carbon $finishedAt,
        int $assignmentsProcessed,
        int $vehiclesMoved,
        int $idleVehicles,
        int $stoppedVehicles,
        int $offlineVehicles,
        int $trackingLogsCreated,
        int $trackingStatesUpdated,
        int $errorsCount = 0
    ): self {
        $durationMs = abs($finishedAt->diffInMilliseconds($startedAt));
        
        return new self(
            cycleId: $cycleId,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationMs: $durationMs,
            assignmentsProcessed: $assignmentsProcessed,
            vehiclesMoved: $vehiclesMoved,
            idleVehicles: $idleVehicles,
            stoppedVehicles: $stoppedVehicles,
            offlineVehicles: $offlineVehicles,
            trackingLogsCreated: $trackingLogsCreated,
            trackingStatesUpdated: $trackingStatesUpdated,
            errorsCount: $errorsCount,
            status: 'completed'
        );
    }

    /**
     * Create a failed tracking cycle result
     */
    public static function failure(
        string $cycleId,
        Carbon $startedAt,
        Carbon $finishedAt,
        int $errorsCount,
        string $errorMessage
    ): self {
        $durationMs = abs($finishedAt->diffInMilliseconds($startedAt));
        
        return new self(
            cycleId: $cycleId,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationMs: $durationMs,
            assignmentsProcessed: 0,
            vehiclesMoved: 0,
            idleVehicles: 0,
            stoppedVehicles: 0,
            offlineVehicles: 0,
            trackingLogsCreated: 0,
            trackingStatesUpdated: 0,
            errorsCount: $errorsCount,
            status: 'failed'
        );
    }

    /**
     * Format the result as a loggable string
     */
    public function toLogString(): string
    {
        return sprintf(
            "Tracking Cycle #%s\n" .
            "Started: %s\n" .
            "Finished: %s\n" .
            "Duration: %d ms\n" .
            "Assignments: %d\n" .
            "Moved: %d\n" .
            "Idle: %d\n" .
            "Stopped: %d\n" .
            "Offline: %d\n" .
            "Tracking Logs: %d\n" .
            "Tracking States: %d\n" .
            "Errors: %d",
            $this->cycleId,
            $this->startedAt->toDateTimeString(),
            $this->finishedAt->toDateTimeString(),
            $this->durationMs,
            $this->assignmentsProcessed,
            $this->vehiclesMoved,
            $this->idleVehicles,
            $this->stoppedVehicles,
            $this->offlineVehicles,
            $this->trackingLogsCreated,
            $this->trackingStatesUpdated,
            $this->errorsCount
        );
    }
}
