<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation;

use App\Enums\AssignmentEvaluationMode;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Services\DriverEvaluation\Detection\DriverEvaluationDetectionResult;
use App\Services\DriverEvaluation\Detection\DriverEvaluationDetectionService;
use App\Services\DriverEvaluation\Exceptions\DriverEvaluationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Operational lifecycle orchestration for Driver Evaluation (DRIVER-EVAL-07).
 *
 * Does not rescore, redesign detectors, or mutate tracking/assignment workflows.
 * Hooks must call these methods outside primary operational transactions.
 */
class DriverEvaluationLifecycleService
{
    public function __construct(
        private readonly AssignmentEvaluationPersistenceService $persistence,
        private readonly DriverEvaluationDetectionService $detection,
    ) {}

    /**
     * Assignment entered In Operation — ensure mutable LIVE evaluation context exists.
     * Does not score or finalize.
     */
    public function openForAssignment(Assignment $assignment): ?AssignmentEvaluation
    {
        if ($assignment->driver_id === null || $assignment->vehicle_id === null || $assignment->shipment_id === null) {
            Log::warning('driver_evaluation.lifecycle.open_skipped_missing_identity', [
                'assignment_id' => $assignment->id,
            ]);

            return null;
        }

        $evaluation = $this->persistence->getOrCreateMutableForAssignment(
            $assignment,
            AssignmentEvaluationMode::LIVE,
            [
                'lifecycle_phase' => 'DRIVER-EVAL-07',
                'opened_via' => 'assignment_in_operation',
            ],
        );

        Log::info('driver_evaluation.lifecycle.opened', [
            'assignment_id' => $assignment->id,
            'evaluation_id' => $evaluation->id,
            'revision' => $evaluation->revision,
            'status' => $evaluation->status?->value,
        ]);

        return $evaluation;
    }

    /**
     * Throttled mid-trip detection (LIVE only). Never finalizes.
     * Safe under repeated GPS submissions.
     */
    public function processIncremental(Assignment $assignment): ?DriverEvaluationDetectionResult
    {
        if (! (bool) config('driver_evaluation.lifecycle.incremental_enabled', true)) {
            return null;
        }

        if (! $assignment->is_active) {
            return null;
        }

        if ($this->hasFinalizedLiveEvaluation((int) $assignment->id)) {
            return null;
        }

        $interval = max(30, (int) config('driver_evaluation.lifecycle.incremental_min_interval_seconds', 120));
        $cacheKey = 'driver_eval:incremental:'.$assignment->id;
        if (! Cache::add($cacheKey, 1, $interval)) {
            return null;
        }

        try {
            $result = $this->detection->processAssignment(
                $assignment,
                AssignmentEvaluationMode::LIVE,
                finalizeWhenDone: false,
            );
        } catch (DriverEvaluationException $e) {
            // DRIVER-EVAL-08G: lose race to finalize → no orphan mutable revision.
            if ($e->statusCode === 409 && $this->hasFinalizedLiveEvaluation((int) $assignment->id)) {
                Log::info('driver_evaluation.lifecycle.incremental_skipped_finalized_race', [
                    'assignment_id' => $assignment->id,
                ]);

                return null;
            }

            throw $e;
        }

        Log::info('driver_evaluation.lifecycle.incremental', [
            'assignment_id' => $assignment->id,
            'evaluation_id' => $result->evaluation->id,
            'telemetry_count' => $result->telemetrySampleCount,
            'events_created' => count($result->eventsCreated),
            'events_existing' => count($result->eventsExisting),
        ]);

        return $result;
    }

    /**
     * Assignment completed — final detection + scoring + aggregation.
     * Idempotent: if a LIVE evaluation is already finalized, skip (no new revision).
     */
    public function finalizeForCompletedAssignment(Assignment $assignment): ?DriverEvaluationDetectionResult
    {
        if ($this->hasFinalizedLiveEvaluation((int) $assignment->id)) {
            Log::info('driver_evaluation.lifecycle.finalize_skipped_already_finalized', [
                'assignment_id' => $assignment->id,
            ]);

            return null;
        }

        // Ensure context exists even if start hook was missed.
        $this->openForAssignment($assignment);

        $result = $this->detection->processAssignment(
            $assignment->fresh(['status', 'vehicle', 'trailer', 'shipment']) ?? $assignment,
            AssignmentEvaluationMode::LIVE,
            finalizeWhenDone: true,
        );

        Log::info('driver_evaluation.lifecycle.finalized', [
            'assignment_id' => $assignment->id,
            'evaluation_id' => $result->evaluation->id,
            'status' => $result->evaluation->status?->value,
            'overall_score' => $result->evaluation->overall_score,
            'telemetry_count' => $result->telemetrySampleCount,
        ]);

        return $result;
    }

    /**
     * Best-effort wrappers for operational hooks — never throw to callers.
     */
    public function openForAssignmentSafely(Assignment $assignment): void
    {
        try {
            $this->openForAssignment($assignment);
        } catch (\Throwable $e) {
            report($e);
            Log::error('driver_evaluation.lifecycle.open_failed', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function processIncrementalSafely(Assignment $assignment): void
    {
        try {
            $this->processIncremental($assignment);
        } catch (\Throwable $e) {
            report($e);
            Log::error('driver_evaluation.lifecycle.incremental_failed', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function finalizeForCompletedAssignmentSafely(Assignment $assignment): void
    {
        try {
            $this->finalizeForCompletedAssignment($assignment);
        } catch (\Throwable $e) {
            report($e);
            Log::error('driver_evaluation.lifecycle.finalize_failed', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function hasFinalizedLiveEvaluation(int $assignmentId): bool
    {
        return AssignmentEvaluation::query()
            ->where('assignment_id', $assignmentId)
            ->where('evaluation_mode', AssignmentEvaluationMode::LIVE->value)
            ->whereNotNull('finalized_at')
            ->exists();
    }
}
