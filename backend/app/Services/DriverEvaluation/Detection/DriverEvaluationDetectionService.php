<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Detection;

use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Services\DriverEvaluation\Aggregation\DriverPerformanceAggregationService;
use App\Services\DriverEvaluation\AssignmentEvaluationPersistenceService;
use App\Services\DriverEvaluation\Exceptions\DriverEvaluationException;
use App\Services\DriverEvaluation\Scoring\DriverEvaluationScoringService;
use App\Services\DriverEvaluation\Scoring\EvaluationScoringContext;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates DRIVER-EVAL-03 evidence detection for one Assignment (replay-safe).
 *
 * Scoring (DRIVER-EVAL-04) runs only when finalizeWhenDone is true.
 * Does not mutate tracking/simulation engines.
 */
class DriverEvaluationDetectionService
{
    /**
     * @param  list<EvaluationEventDetector>  $detectors
     */
    public function __construct(
        private readonly AssignmentEvaluationPersistenceService $persistence,
        private readonly EvaluationTelemetryLoader $telemetryLoader,
        private readonly DriverEvaluationScoringService $scoring,
        private readonly DriverPerformanceAggregationService $aggregation,
        private readonly array $detectors,
    ) {}

    /**
     * Default detector set for the application container.
     *
     * @return list<EvaluationEventDetector>
     */
    public static function defaultDetectors(
        SpeedingDetector $speeding,
        HarshBrakingDetector $harshBraking,
        HarshAccelerationDetector $harshAcceleration,
        SuddenStopDetector $suddenStop,
        IdleStopDetector $idleStop,
        RouteDeviationEvaluationDetector $routeDeviation,
        TimingEvaluationDetector $timing,
        WorkflowEvaluationDetector $workflow,
    ): array {
        return [
            $speeding,
            $harshBraking,
            $harshAcceleration,
            $suddenStop,
            $idleStop,
            $routeDeviation,
            $timing,
            $workflow,
        ];
    }

    public function processAssignment(
        Assignment $assignment,
        AssignmentEvaluationMode $mode = AssignmentEvaluationMode::LIVE,
        bool $finalizeWhenDone = false,
    ): DriverEvaluationDetectionResult {
        $assignment->loadMissing(['status', 'vehicle', 'trailer', 'shipment']);

        if ($assignment->driver_id === null || $assignment->vehicle_id === null || $assignment->shipment_id === null) {
            throw new DriverEvaluationException(
                'Assignment lacks identity required for evaluation detection.',
                422,
            );
        }

        $evaluation = $this->persistence->getOrCreateMutableForAssignment($assignment, $mode, [
            'detection_phase' => 'DRIVER-EVAL-03',
        ]);

        if ($evaluation->status === AssignmentEvaluationStatus::PENDING) {
            $this->persistence->markProcessing($evaluation);
            $evaluation = $evaluation->fresh();
        }

        $logs = $this->telemetryLoader->loadForAssignment($assignment, $mode);
        $skipped = [];
        $created = [];
        $existing = [];

        // Persist telemetry sample count for scoring evidence quality (DRIVER-EVAL-04).
        // DRIVER-EVAL-08G: never write metadata on a finalized evaluation.
        $this->persistence->assertMutable($evaluation);
        $meta = $evaluation->metadata ?? [];
        $meta['telemetry_sample_count'] = $logs->count();
        $evaluation->metadata = $meta;
        $evaluation->save();

        if ($logs->isEmpty()) {
            $skipped[] = [
                'reason' => 'insufficient_telemetry',
                'detail' => 'No mode-eligible attributed telemetry samples in assignment window.',
            ];
        }

        foreach ($this->detectors as $detector) {
            $candidates = $detector->detect($assignment, $evaluation, $logs);
            foreach ($candidates as $candidate) {
                try {
                    $result = $this->persistence->recordEvent(
                        evaluation: $evaluation,
                        eventCode: $candidate->eventCode,
                        evidenceSource: $candidate->evidenceSource,
                        detectorCode: $candidate->detectorCode,
                        detectorVersion: $candidate->detectorVersion,
                        occurredAt: $candidate->occurredAt,
                        trackingLogIds: $candidate->trackingLogIds,
                        evidenceMetadata: $candidate->evidenceMetadata,
                        windowStart: $candidate->windowStart,
                        windowEnd: $candidate->windowEnd,
                    );
                    if ($result['created']) {
                        $created[] = $result['event'];
                    } else {
                        $existing[] = $result['event'];
                    }
                } catch (DriverEvaluationException $e) {
                    $skipped[] = [
                        'reason' => 'persist_rejected',
                        'detail' => $e->getMessage(),
                    ];
                }
            }
        }

        Log::info('driver_evaluation.detection.processed', [
            'assignment_id' => $assignment->id,
            'evaluation_id' => $evaluation->id,
            'mode' => $mode->value,
            'telemetry_count' => $logs->count(),
            'events_created' => count($created),
            'events_existing' => count($existing),
            'skipped' => count($skipped),
        ]);

        if ($finalizeWhenDone) {
            $evaluation = $this->finalizeAfterDetection(
                $evaluation->fresh(),
                $assignment,
                $logs->count(),
                count($created) + count($existing),
            );
        }

        return new DriverEvaluationDetectionResult(
            evaluation: $evaluation->fresh(),
            eventsCreated: $created,
            eventsExisting: $existing,
            telemetrySampleCount: $logs->count(),
            skipped: $skipped,
        );
    }

    /**
     * @param  iterable<int, Assignment|int>  $assignments
     * @return list<DriverEvaluationDetectionResult>
     */
    public function processAssignments(
        iterable $assignments,
        AssignmentEvaluationMode $mode = AssignmentEvaluationMode::LIVE,
        bool $finalizeWhenDone = false,
    ): array {
        $results = [];
        foreach ($assignments as $item) {
            $assignment = $item instanceof Assignment
                ? $item
                : Assignment::query()->find($item);
            if ($assignment === null) {
                continue;
            }
            $results[] = $this->processAssignment($assignment, $mode, $finalizeWhenDone);
        }

        return $results;
    }

    private function finalizeAfterDetection(
        AssignmentEvaluation $evaluation,
        Assignment $assignment,
        int $telemetryCount,
        int $eventCount,
    ): AssignmentEvaluation {
        if ($evaluation->isFinalized()) {
            return $evaluation;
        }

        $shipment = $assignment->shipment;
        $context = new EvaluationScoringContext(
            telemetrySampleCount: $telemetryCount,
            routeGeometryAvailable: $shipment !== null
                && app(\App\Simulation\Routing\Application\Services\PlannedRouteCache::class)
                    ->has((string) $shipment->id),
            timingDataAvailable: $shipment !== null && (
                $shipment->planned_pickup_date !== null
                || $shipment->planned_delivery_date !== null
                || $shipment->actual_delivery_date !== null
            ),
            workflowDataAvailable: $assignment->status_id !== null || $assignment->execution_phase !== null,
        );

        if ($telemetryCount === 0 && $eventCount === 0) {
            $incomplete = $this->persistence->markIncomplete(
                $evaluation,
                AssignmentEvaluationEvidenceQuality::INSUFFICIENT,
                'No live telemetry samples available for evaluation.',
            );
            $this->aggregation->refreshForDriver(
                $incomplete->driver_id !== null ? (int) $incomplete->driver_id : null
            );

            return $incomplete;
        }

        return $this->scoring->scoreAndFinalize($evaluation, $context);
    }
}
