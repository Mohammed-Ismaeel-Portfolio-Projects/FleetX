<?php

namespace App\Actions\Tracking;

use App\Core\ApiResult;
use App\Events\Tracking\TrackingStateUpdated;
use App\Models\TrackingLog;
use App\Models\TrackingState;
use Illuminate\Support\Facades\DB;

class UpdateTrackingStateAction
{
    /**
     * Update or create tracking state based on latest tracking log.
     *
     * Ensures exactly one TrackingState exists per Assignment,
     * containing the latest valid GPS and telemetry data.
     */
    public function execute(TrackingLog $trackingLog): ApiResult
    {
        try {
            return DB::transaction(function () use ($trackingLog) {
                $shipment = $trackingLog->shipment;
                if (! $shipment) {
                    return ApiResult::error('Shipment not found', 404);
                }

                // Prefer assignment matching this shipment that is active (operational).
                $assignment = $shipment->activeAssignment()
                    ->with(['vehicle', 'driver', 'trailer'])
                    ->first();

                if (! $assignment) {
                    return ApiResult::error('No active assignment found for shipment', 404);
                }

                // Determine movement status based on speed
                $movementStatus = $this->determineMovementStatus((float) ($trackingLog->speed ?? 0));

                // Update or create exactly one tracking state per assignment
                $state = TrackingState::updateOrCreate(
                    ['assignment_id' => $assignment->id],
                    [
                        'shipment_id' => $assignment->shipment_id,
                        'vehicle_id' => $assignment->vehicle_id,
                        'driver_id' => $assignment->driver_id,
                        'trailer_id' => $assignment->trailer_id,
                        'latitude' => $trackingLog->latitude,
                        'longitude' => $trackingLog->longitude,
                        'speed' => $trackingLog->speed ?? 0,
                        'heading' => $trackingLog->heading,
                        'accuracy' => $trackingLog->accuracy,
                        'movement_status' => $movementStatus,
                        'online_status' => 'Online', // Online because we just got a GPS fix
                        'signal_quality' => $this->determineSignalQuality((float) ($trackingLog->accuracy ?? 50)),
                        'last_update_at' => $trackingLog->received_at ?? now(),
                    ]
                );

                // Domain event is best-effort — telemetry persistence must not fail closed on event bugs.
                try {
                    TrackingStateUpdated::dispatch($state);
                } catch (\Throwable $e) {
                    report($e);
                }

                return ApiResult::success($state, 'Tracking state updated successfully');
            });
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to update tracking state: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Determine movement status based on speed.
     */
    private function determineMovementStatus(?float $speed): string
    {
        if ($speed === null) {
            return 'Idle';
        }

        $speed = (float) $speed;

        if ($speed > 5) {
            return 'Moving';
        } elseif ($speed > 0.5) {
            return 'Stopped';
        } else {
            return 'Idle';
        }
    }

    /**
     * Determine signal quality based on accuracy.
     * Lower accuracy = better signal.
     */
    private function determineSignalQuality(?float $accuracy): string
    {
        if ($accuracy === null) {
            return 'Weak';
        }

        $accuracy = (float) $accuracy;

        if ($accuracy <= 10) {
            return 'Good';
        } elseif ($accuracy <= 30) {
            return 'Weak';
        } else {
            return 'Lost';
        }
    }
}
