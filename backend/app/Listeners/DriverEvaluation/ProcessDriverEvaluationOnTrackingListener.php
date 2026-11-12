<?php

declare(strict_types=1);

namespace App\Listeners\DriverEvaluation;

use App\Events\Tracking\TrackingStateUpdated;
use App\Listeners\BaseListener;
use App\Models\Assignment;
use App\Services\DriverEvaluation\DriverEvaluationLifecycleService;
use App\Support\DriverAssignmentTransitionMap;
use Illuminate\Support\Facades\Log;

/**
 * Queued incremental evaluation detection after live tracking (DRIVER-EVAL-07).
 * Must not block GPS ingestion. Throttling lives in DriverEvaluationLifecycleService.
 */
class ProcessDriverEvaluationOnTrackingListener extends BaseListener
{
    public function __construct(
        private readonly DriverEvaluationLifecycleService $lifecycle,
    ) {}

    public function handle(object $event): void
    {
        if (! $event instanceof TrackingStateUpdated) {
            return;
        }

        if (! (bool) config('driver_evaluation.lifecycle.incremental_enabled', true)) {
            return;
        }

        $assignmentId = $event->assignmentId;
        if ($assignmentId === null || $assignmentId === '') {
            return;
        }

        $assignment = Assignment::query()
            ->with(['status', 'vehicle', 'trailer', 'shipment'])
            ->find($assignmentId);

        if ($assignment === null || ! $assignment->is_active) {
            return;
        }

        if ($assignment->status?->name !== DriverAssignmentTransitionMap::IN_OPERATION) {
            return;
        }

        // LIVE path only: TrackingStateUpdated from driver telemetry has assignment_id.
        // Simulation may also update state; detection still filters LIVE mode evidence.
        try {
            $this->lifecycle->processIncremental($assignment);
        } catch (\Throwable $e) {
            report($e);
            Log::error('driver_evaluation.lifecycle.tracking_listener_failed', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
