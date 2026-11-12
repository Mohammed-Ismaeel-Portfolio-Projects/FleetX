<?php

namespace App\Services\TrackingSimulator;

use App\Actions\Tracking\CreateTrackingLogAction;
use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Simulation\Lifecycle\ShipmentLifecycleSnapshot;
use App\Simulation\Telemetry\TelemetrySnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * TrackingSimulatorService
 *
 * Persists GPS TrackingLog / TrackingState from Fleet Telemetry snapshots.
 * Consumes shipment lifecycle stage (already advanced by ShipmentLifecycleEngine).
 */
class TrackingSimulatorService
{
    /**
     * @var array<string, TelemetrySnapshot|array<string, mixed>>
     */
    private array $telemetryByVehicleId = [];

    /**
     * @var array<string, mixed>
     */
    private array $lifecycleByShipmentId = [];

    public function __construct(
        private CreateTrackingLogAction $createTrackingLogAction,
        private EuropeanFleetLocations $fleetLocations,
    ) {}

    /**
     * @param array<string, TelemetrySnapshot|array<string, mixed>> $telemetryByVehicleId
     * @param array<string, mixed> $lifecycleByShipmentId
     * @return array{vehicles_processed: int, tracking_logs_created: int, vehicles_moved: int, idle_vehicles: int, stopped_vehicles: int, offline_vehicles: int}
     */
    public function simulate(array $telemetryByVehicleId = [], array $lifecycleByShipmentId = []): array
    {
        $this->telemetryByVehicleId = $telemetryByVehicleId;
        $this->lifecycleByShipmentId = $lifecycleByShipmentId;

        $activeAssignments = SimulationEligibleAssignments::query()
            ->with(['shipment.status', 'vehicle', 'driver', 'trailer'])
            ->get();

        $vehiclesProcessed = 0;
        $trackingLogsCreated = 0;
        $vehiclesMoved = 0;
        $idleVehicles = 0;
        $stoppedVehicles = 0;
        $offlineVehicles = 0;

        foreach ($activeAssignments as $assignment) {
            if (!$assignment->vehicle || !$assignment->shipment) {
                continue;
            }

            $snapshot = $this->resolveSnapshot((string) $assignment->vehicle_id);
            if ($snapshot === null) {
                $offlineVehicles++;
                continue;
            }

            $state = $snapshot->vehicleState;

            if ($state === 'offline') {
                $offlineVehicles++;
                continue;
            }

            $latitude = $snapshot->latitude;
            $longitude = $snapshot->longitude;

            if ($snapshot->fromFallbackCorridor) {
                $safe = $this->fleetLocations->ensureValidPosition(
                    $latitude,
                    $longitude,
                    $assignment->shipment->pickup_city
                );
                $latitude = $safe['latitude'];
                $longitude = $safe['longitude'];
            }

            $recordedAt = $this->resolveRecordedAt($snapshot->timestamp);
            $lifecycle = $this->resolveLifecycle((string) $assignment->shipment_id);

            if ($lifecycle !== null) {
                // Ensure shipment relation reflects lifecycle-synced DB status.
                $assignment->shipment->refresh();
                $assignment->load('shipment.status');

                Log::info('Tracking consuming shipment stage', [
                    'shipment_id' => $assignment->shipment_id,
                    'lifecycle_stage' => $lifecycle->currentStage,
                    'shipment_status' => $assignment->shipment->status?->name,
                ]);
            }

            $trackingResult = $this->createTrackingLogAction->execute([
                'shipment_id' => $assignment->shipment_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'speed' => $snapshot->currentSpeedKmh,
                'heading' => $snapshot->heading,
                'accuracy' => $snapshot->gpsAccuracy,
                'recorded_at' => $recordedAt,
            ]);

            if ($trackingResult->success) {
                $vehiclesProcessed++;
                $trackingLogsCreated++;

                switch ($state) {
                    case 'moving':
                        $vehiclesMoved++;
                        break;
                    case 'idle':
                        $idleVehicles++;
                        break;
                    case 'stopped':
                        $stoppedVehicles++;
                        break;
                }
            }
        }

        return [
            'vehicles_processed' => $vehiclesProcessed,
            'tracking_logs_created' => $trackingLogsCreated,
            'vehicles_moved' => $vehiclesMoved,
            'idle_vehicles' => $idleVehicles,
            'stopped_vehicles' => $stoppedVehicles,
            'offline_vehicles' => $offlineVehicles,
        ];
    }

    private function resolveSnapshot(string $vehicleId): ?TelemetrySnapshot
    {
        $cached = $this->telemetryByVehicleId[$vehicleId] ?? null;

        if ($cached instanceof TelemetrySnapshot) {
            return $cached;
        }

        if (is_array($cached) && ($cached['_snapshot'] ?? null) instanceof TelemetrySnapshot) {
            return $cached['_snapshot'];
        }

        if (is_array($cached) && isset($cached['vehicle_id'], $cached['latitude'], $cached['longitude'])) {
            return TelemetrySnapshot::fromArray($cached);
        }

        return null;
    }

    private function resolveLifecycle(string $shipmentId): ?ShipmentLifecycleSnapshot
    {
        $entry = $this->lifecycleByShipmentId[$shipmentId] ?? null;

        if ($entry instanceof ShipmentLifecycleSnapshot) {
            return $entry;
        }

        if (is_array($entry) && ($entry['_snapshot'] ?? null) instanceof ShipmentLifecycleSnapshot) {
            return $entry['_snapshot'];
        }

        if (is_array($entry) && isset($entry['current_stage'])) {
            return ShipmentLifecycleSnapshot::fromArray($entry);
        }

        return null;
    }

    private function resolveRecordedAt(string $timestamp): string
    {
        try {
            return Carbon::parse($timestamp)->toDateTimeString();
        } catch (\Throwable) {
            return now()->toDateTimeString();
        }
    }
}
