<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Aggregation;

use App\Enums\DriverPerformanceMaturity;
use App\Models\Driver;
use App\Models\DriverScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aggregates finalized AssignmentEvaluations into the driver_scores projection (DRIVER-EVAL-05).
 *
 * Does not mutate AssignmentEvaluation / events.
 * Does not query tracking telemetry, detectors, or PricingEngine.
 */
class DriverPerformanceAggregationService
{
    public function __construct(
        private readonly EligibleEvaluationSelector $selector,
        private readonly DriverPerformanceAggregationEngine $engine,
    ) {}

    /**
     * Calculate without persisting.
     */
    public function calculate(int $driverId): DriverPerformanceResult
    {
        $latest = $this->selector->latestFinalizedForDriver($driverId);

        return $this->engine->aggregate(
            $driverId,
            $latest,
            $this->selector->scoredOnly($latest),
            $this->selector->incompleteOnly($latest),
        );
    }

    /**
     * Idempotent upsert of the authoritative driver_scores projection.
     */
    public function aggregateForDriver(int $driverId): DriverScore
    {
        if (! Driver::query()->whereKey($driverId)->exists()) {
            throw new \InvalidArgumentException("Driver {$driverId} does not exist.");
        }

        $result = $this->calculate($driverId);

        return DB::transaction(function () use ($driverId, $result) {
            /** @var DriverScore $row */
            $row = DriverScore::query()->lockForUpdate()->firstOrNew(['driver_id' => $driverId]);
            $row->driver_id = $driverId;
            $row->score = $result->overallScore;
            $row->score_label = $result->scoreLabel?->value;
            $row->confidence = $result->confidence;
            $row->maturity = $result->maturity;
            $row->trend = $result->trend;
            $row->recent_score = $result->recentScore;
            $row->previous_score = $result->previousScore;
            $row->trend_delta = $result->trendDelta;
            $row->evaluated_assignments_count = $result->evaluatedAssignmentsCount;
            $row->incomplete_assignments_count = $result->incompleteAssignmentsCount;
            $row->total_deliveries = $result->totalDeliveries;
            $row->late_deliveries = $result->lateDeliveries;
            $row->category_scores = $result->categoryScores;
            $row->aggregation_version = $result->aggregationVersion;
            $row->aggregation_metadata = $result->metadata;
            $row->last_evaluated_at = $result->lastEvaluatedAt
                ? \Illuminate\Support\Carbon::parse($result->lastEvaluatedAt)
                : null;
            $row->aggregated_at = now();
            $row->save();

            Log::info('driver_evaluation.aggregation.upserted', [
                'driver_id' => $driverId,
                'score' => $row->score,
                'maturity' => $result->maturity->value,
                'trend' => $result->trend->value,
                'evaluated_assignments_count' => $result->evaluatedAssignmentsCount,
                'aggregation_version' => $result->aggregationVersion,
            ]);

            return $row->refresh();
        });
    }

    /**
     * Refresh projection after an evaluation finalizes for a driver.
     */
    public function refreshForDriver(?int $driverId): ?DriverScore
    {
        if ($driverId === null) {
            return null;
        }

        return $this->aggregateForDriver($driverId);
    }

    /**
     * Clear numeric score when maturity is unavailable (explicit null, not zero).
     */
    public function ensureUnavailableProjection(int $driverId): DriverScore
    {
        $score = $this->aggregateForDriver($driverId);
        if ($score->maturity === DriverPerformanceMaturity::UNAVAILABLE) {
            $this->assertScoreIsNullNotZero($score);
        }

        return $score;
    }

    private function assertScoreIsNullNotZero(DriverScore $score): void
    {
        if ($score->score !== null) {
            throw new \LogicException('Unavailable driver performance must not carry a numeric score.');
        }
    }
}
