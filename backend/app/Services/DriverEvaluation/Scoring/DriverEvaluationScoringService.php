<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Scoring;

use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\AssignmentEvaluationStatus;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\Shipment;
use App\Services\DriverEvaluation\AssignmentEvaluationPersistenceService;
use App\Services\DriverEvaluation\Exceptions\DriverEvaluationException;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scores persisted evaluation events and finalizes AssignmentEvaluation (DRIVER-EVAL-04).
 * After finalize, refreshes the driver_scores projection (DRIVER-EVAL-05).
 * Does not run detection. Does not mutate evaluation events after finalize.
 */
class DriverEvaluationScoringService
{
    public function __construct(
        private readonly DriverEvaluationScoringEngine $engine,
        private readonly AssignmentEvaluationPersistenceService $persistence,
        private readonly PlannedRouteCache $plannedRouteCache,
        private readonly \App\Services\DriverEvaluation\Aggregation\DriverPerformanceAggregationService $aggregation,
    ) {}

    /**
     * Calculate score from persisted events without mutating the evaluation.
     */
    public function calculate(
        AssignmentEvaluation $evaluation,
        ?EvaluationScoringContext $context = null,
    ): AssignmentEvaluationScoreResult {
        $evaluation->loadMissing(['events', 'assignment.shipment', 'assignment.status']);
        $context ??= $this->buildContext($evaluation);

        return $this->engine->score($evaluation->events, $context);
    }

    /**
     * Score + finalize atomically. Creates explainable immutable result.
     */
    public function scoreAndFinalize(
        AssignmentEvaluation $evaluation,
        ?EvaluationScoringContext $context = null,
        ?AssignmentEvaluationEvidenceQuality $quality = null,
    ): AssignmentEvaluation {
        $this->persistence->assertMutable($evaluation);

        $evaluation->loadMissing(['events', 'assignment.shipment', 'assignment.status']);
        $context ??= $this->buildContext($evaluation);
        $result = $this->engine->score($evaluation->events, $context);

        $final = DB::transaction(function () use ($evaluation, $result, $quality, $context) {
            $locked = AssignmentEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();
            $this->persistence->assertMutable($locked);

            if (! $result->isScoreAvailable) {
                $meta = $locked->metadata ?? [];
                $meta['incomplete_reason'] = $meta['incomplete_reason']
                    ?? 'No evaluable scoring categories — insufficient evidence.';
                $locked->metadata = $meta;

                $finalized = $this->persistence->applyScoreResult(
                    $locked,
                    $result,
                    $quality ?? AssignmentEvaluationEvidenceQuality::INSUFFICIENT,
                    AssignmentEvaluationStatus::INCOMPLETE,
                );

                Log::info('driver_evaluation.scoring.incomplete', [
                    'evaluation_id' => $finalized->id,
                    'assignment_id' => $finalized->assignment_id,
                    'confidence' => $result->confidence->value,
                    'scoring_version' => $result->scoringVersion,
                ]);

                return $finalized;
            }

            $finalized = $this->persistence->applyScoreResult(
                $locked,
                $result,
                $quality ?? $this->inferQuality($context, $result),
                AssignmentEvaluationStatus::COMPLETED,
            );

            Log::info('driver_evaluation.scoring.finalized', [
                'evaluation_id' => $finalized->id,
                'assignment_id' => $finalized->assignment_id,
                'driver_id' => $finalized->driver_id,
                'overall_score' => $finalized->overall_score,
                'confidence' => $result->confidence->value,
                'scoring_version' => $result->scoringVersion,
                'event_count' => $result->metadata['event_count'] ?? null,
            ]);

            return $finalized;
        });

        $this->aggregation->refreshForDriver($final->driver_id !== null ? (int) $final->driver_id : null);

        return $final;
    }

    public function buildContext(AssignmentEvaluation $evaluation): EvaluationScoringContext
    {
        $assignment = $evaluation->assignment;
        $shipment = $assignment?->shipment;

        $telemetryCount = 0;
        foreach ($evaluation->events as $event) {
            $ids = $event->evidence_tracking_log_ids ?? [];
            $telemetryCount = max($telemetryCount, is_array($ids) ? count($ids) : 0);
        }

        // Prefer detection-phase metadata when present.
        $metaCount = (int) (($evaluation->metadata['telemetry_sample_count'] ?? 0));
        if ($metaCount > $telemetryCount) {
            $telemetryCount = $metaCount;
        }

        $routeAvailable = false;
        if ($evaluation->shipment_id) {
            $routeAvailable = $this->plannedRouteCache->has((string) $evaluation->shipment_id);
        }

        $timingAvailable = false;
        if ($shipment instanceof Shipment) {
            $timingAvailable = $shipment->planned_pickup_date !== null
                || $shipment->planned_delivery_date !== null
                || $shipment->actual_delivery_date !== null;
        }

        $workflowAvailable = $assignment instanceof Assignment
            && ($assignment->status_id !== null || $assignment->execution_phase !== null);

        return new EvaluationScoringContext(
            telemetrySampleCount: $telemetryCount,
            routeGeometryAvailable: $routeAvailable,
            timingDataAvailable: $timingAvailable,
            workflowDataAvailable: $workflowAvailable,
        );
    }

    private function inferQuality(
        EvaluationScoringContext $context,
        AssignmentEvaluationScoreResult $result,
    ): AssignmentEvaluationEvidenceQuality {
        $evaluable = count(array_filter(
            $result->categories,
            static fn (array $c) => $c['evaluable']
        ));

        if ($evaluable >= 4 && $context->telemetrySampleCount >= (int) config('driver_evaluation.scoring.min_telemetry_samples', 3)) {
            return AssignmentEvaluationEvidenceQuality::SUFFICIENT;
        }

        if ($evaluable >= 2) {
            return AssignmentEvaluationEvidenceQuality::PARTIAL;
        }

        return AssignmentEvaluationEvidenceQuality::INSUFFICIENT;
    }
}
