<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation;

use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\AssignmentEvaluationMode;
use App\Enums\AssignmentEvaluationStatus;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationEventSeverity;
use App\Enums\DriverEvaluationEvidenceSource;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\AssignmentEvaluationEvent;
use App\Services\DriverEvaluation\Exceptions\DriverEvaluationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Persistence contract for AssignmentEvaluation + events (DRIVER-EVAL-02).
 *
 * No scoring. No detection. Idempotent event recording + immutability guards only.
 */
class AssignmentEvaluationPersistenceService
{
    public function __construct(
        private readonly AssignmentTelemetryAttributionService $attribution,
    ) {}

    /**
     * Reuse the latest mutable evaluation for assignment+mode, or create revision 1 / next.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function getOrCreateMutableForAssignment(
        Assignment $assignment,
        AssignmentEvaluationMode $mode = AssignmentEvaluationMode::LIVE,
        array $metadata = [],
    ): AssignmentEvaluation {
        $existing = AssignmentEvaluation::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_mode', $mode->value)
            ->whereIn('status', [
                AssignmentEvaluationStatus::PENDING->value,
                AssignmentEvaluationStatus::PROCESSING->value,
            ])
            ->whereNull('finalized_at')
            ->orderByDesc('revision')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // DRIVER-EVAL-08G: do not open a post-finalize orphan mutable LIVE revision
        // (incremental race). Explicit reprocess must use createForAssignment + supersedes.
        $alreadyFinalized = AssignmentEvaluation::query()
            ->where('assignment_id', $assignment->id)
            ->where('evaluation_mode', $mode->value)
            ->whereNotNull('finalized_at')
            ->exists();

        if ($alreadyFinalized) {
            throw new DriverEvaluationException(
                'Cannot open mutable evaluation; a finalized evaluation already exists for this assignment mode. Create a superseding revision to reprocess.',
                409,
                ['assignment_id' => $assignment->id, 'evaluation_mode' => $mode->value],
            );
        }

        return $this->createForAssignment($assignment, $mode, $metadata);
    }

    /**
     * Create a mutable evaluation snapshot from an Assignment.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function createForAssignment(
        Assignment $assignment,
        AssignmentEvaluationMode $mode = AssignmentEvaluationMode::LIVE,
        array $metadata = [],
        ?AssignmentEvaluation $supersedes = null,
    ): AssignmentEvaluation {
        if ($assignment->driver_id === null || $assignment->vehicle_id === null || $assignment->shipment_id === null) {
            throw new DriverEvaluationException(
                'Assignment is missing driver, vehicle, or shipment identity required for evaluation.',
                422,
            );
        }

        $nextRevision = 1;
        if ($supersedes !== null) {
            $nextRevision = (int) $supersedes->revision + 1;
        } else {
            $max = AssignmentEvaluation::query()
                ->where('assignment_id', $assignment->id)
                ->max('revision');
            $nextRevision = $max ? ((int) $max + 1) : 1;
        }

        return AssignmentEvaluation::query()->create([
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $assignment->driver_id,
            'vehicle_id' => $assignment->vehicle_id,
            'evaluation_mode' => $mode,
            'status' => AssignmentEvaluationStatus::PENDING,
            'evidence_quality' => null,
            'revision' => $nextRevision,
            'supersedes_evaluation_id' => $supersedes?->id,
            'started_at' => now(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * Mark evaluation as incomplete due to missing/ambiguous evidence — not a negative score.
     */
    public function markIncomplete(
        AssignmentEvaluation $evaluation,
        AssignmentEvaluationEvidenceQuality $quality,
        ?string $reason = null,
    ): AssignmentEvaluation {
        $this->assertMutable($evaluation);

        $metadata = $evaluation->metadata ?? [];
        if ($reason !== null) {
            $metadata['incomplete_reason'] = $reason;
        }

        $evaluation->status = AssignmentEvaluationStatus::INCOMPLETE;
        $evaluation->evidence_quality = $quality;
        $evaluation->completed_at = now();
        $evaluation->finalized_at = now();
        $evaluation->metadata = $metadata;
        $evaluation->save();

        return $evaluation->refresh();
    }

    public function markProcessing(AssignmentEvaluation $evaluation): AssignmentEvaluation
    {
        $this->assertMutable($evaluation);
        $evaluation->status = AssignmentEvaluationStatus::PROCESSING;
        $evaluation->save();

        return $evaluation->refresh();
    }

    /**
     * Finalize as completed (sufficient evidence). Does not compute scores.
     * Prefer DriverEvaluationScoringService::scoreAndFinalize for scored finalization.
     */
    public function finalizeCompleted(
        AssignmentEvaluation $evaluation,
        AssignmentEvaluationEvidenceQuality $quality = AssignmentEvaluationEvidenceQuality::SUFFICIENT,
    ): AssignmentEvaluation {
        $this->assertMutable($evaluation);

        $evaluation->status = AssignmentEvaluationStatus::COMPLETED;
        $evaluation->evidence_quality = $quality;
        $evaluation->completed_at = now();
        $evaluation->finalized_at = now();
        $evaluation->save();

        return $evaluation->refresh();
    }

    /**
     * Persist a scored finalization result (DRIVER-EVAL-04). Atomic with caller transaction.
     */
    public function applyScoreResult(
        AssignmentEvaluation $evaluation,
        \App\Services\DriverEvaluation\Scoring\AssignmentEvaluationScoreResult $result,
        AssignmentEvaluationEvidenceQuality $quality,
        AssignmentEvaluationStatus $status,
    ): AssignmentEvaluation {
        $this->assertMutable($evaluation);

        $evaluation->overall_score = $result->overallScore;
        $evaluation->score_label = $result->scoreLabel?->value;
        $evaluation->confidence = $result->confidence;
        $evaluation->scoring_version = $result->scoringVersion;
        $evaluation->category_scores = $result->categoryScoresPayload();
        $evaluation->scoring_metadata = $result->metadata;
        $evaluation->evidence_quality = $quality;
        $evaluation->status = $status;
        $evaluation->completed_at = now();
        $evaluation->finalized_at = now();
        $evaluation->save();

        return $evaluation->refresh();
    }

    /**
     * Idempotent event persistence. Returns existing row when idempotency_key matches.
     *
     * @param  list<int|string>  $trackingLogIds
     * @param  array<string, mixed>  $evidenceMetadata
     * @return array{event: AssignmentEvaluationEvent, created: bool}
     */
    public function recordEvent(
        AssignmentEvaluation $evaluation,
        DriverEvaluationEventCode $eventCode,
        DriverEvaluationEvidenceSource $evidenceSource,
        string $detectorCode,
        string $detectorVersion,
        Carbon|string $occurredAt,
        array $trackingLogIds = [],
        array $evidenceMetadata = [],
        ?Carbon $windowStart = null,
        ?Carbon $windowEnd = null,
        ?DriverEvaluationEventSeverity $severity = null,
        ?string $idempotencyKey = null,
    ): array {
        $this->assertMutable($evaluation);

        if (! $eventCode->isCurrentlySupported()) {
            throw new DriverEvaluationException(
                "Event code {$eventCode->value} is not currently supported.",
                422,
            );
        }

        if (! $this->attribution->sourceAllowedForMode($evidenceSource, $evaluation->evaluation_mode->value)) {
            throw new DriverEvaluationException(
                "Evidence source {$evidenceSource->value} is not allowed for evaluation mode {$evaluation->evaluation_mode->value}.",
                422,
            );
        }

        $normalizedIds = array_values(array_unique(array_map(
            static fn ($id) => (int) $id,
            $trackingLogIds,
        )));
        sort($normalizedIds);

        $occurred = $occurredAt instanceof Carbon ? $occurredAt : Carbon::parse($occurredAt);

        $key = $idempotencyKey ?? $this->buildIdempotencyKey(
            (int) $evaluation->assignment_id,
            $eventCode,
            $detectorCode,
            $detectorVersion,
            $evidenceSource,
            $normalizedIds,
            $windowStart,
            $windowEnd,
        );

        $existing = AssignmentEvaluationEvent::query()
            ->where('idempotency_key', $key)
            ->first();

        if ($existing !== null) {
            return ['event' => $existing, 'created' => false];
        }

        try {
            $event = DB::transaction(function () use (
                $evaluation,
                $eventCode,
                $evidenceSource,
                $detectorCode,
                $detectorVersion,
                $occurred,
                $normalizedIds,
                $evidenceMetadata,
                $windowStart,
                $windowEnd,
                $severity,
                $key,
            ) {
                return AssignmentEvaluationEvent::query()->create([
                    'assignment_evaluation_id' => $evaluation->id,
                    'assignment_id' => $evaluation->assignment_id,
                    'shipment_id' => $evaluation->shipment_id,
                    'driver_id' => $evaluation->driver_id,
                    'vehicle_id' => $evaluation->vehicle_id,
                    'event_code' => $eventCode,
                    'category' => $eventCode->category(),
                    'severity' => $severity ?? $eventCode->defaultSeverity(),
                    'occurred_at' => $occurred,
                    'window_start_at' => $windowStart,
                    'window_end_at' => $windowEnd,
                    'evidence_source' => $evidenceSource,
                    'detector_code' => $detectorCode,
                    'detector_version' => $detectorVersion,
                    'idempotency_key' => $key,
                    'evidence_tracking_log_ids' => $normalizedIds === [] ? null : $normalizedIds,
                    'evidence_metadata' => $evidenceMetadata === [] ? null : $evidenceMetadata,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $existing = AssignmentEvaluationEvent::query()
                ->where('idempotency_key', $key)
                ->first();
            if ($existing !== null) {
                return ['event' => $existing, 'created' => false];
            }
            throw $e;
        }

        return ['event' => $event, 'created' => true];
    }

    /**
     * @param  list<int>  $trackingLogIds
     */
    public function buildIdempotencyKey(
        int $assignmentId,
        DriverEvaluationEventCode $eventCode,
        string $detectorCode,
        string $detectorVersion,
        DriverEvaluationEvidenceSource $source,
        array $trackingLogIds,
        ?Carbon $windowStart,
        ?Carbon $windowEnd,
    ): string {
        $fingerprint = hash('sha256', implode('|', [
            implode(',', $trackingLogIds),
            $windowStart?->toIso8601String() ?? '',
            $windowEnd?->toIso8601String() ?? '',
        ]));

        return implode(':', [
            (string) $assignmentId,
            $eventCode->value,
            $detectorCode,
            $detectorVersion,
            $source->value,
            substr($fingerprint, 0, 32),
        ]);
    }

    public function assertMutable(AssignmentEvaluation $evaluation): void
    {
        if ($evaluation->isFinalized() || ! $evaluation->isMutable()) {
            throw new DriverEvaluationException(
                'Finalized assignment evaluations are immutable. Create a new revision to reprocess.',
                409,
                ['evaluation_id' => $evaluation->id, 'status' => $evaluation->status?->value],
            );
        }
    }
}
