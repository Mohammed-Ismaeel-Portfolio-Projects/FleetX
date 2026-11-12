<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Aggregation;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\AssignmentEvaluationEvidenceQuality;
use App\Enums\DriverEvaluationScoreLabel;
use App\Enums\DriverEvaluationScoringCategory;
use App\Enums\DriverPerformanceMaturity;
use App\Enums\DriverPerformanceTrend;
use App\Models\AssignmentEvaluation;
use Illuminate\Support\Collection;

/**
 * Pure, deterministic driver performance aggregation (DRIVER-EVAL-05).
 *
 * Strategy: equal-weight mean of eligible Assignment overall scores +
 * explicit recent-vs-previous trend. Confidence is reporting-only (not a
 * numeric weight). Does not rescore assignments.
 */
final class DriverPerformanceAggregationEngine
{
    /**
     * @param  Collection<int, AssignmentEvaluation>  $latestFinalized
     * @param  Collection<int, AssignmentEvaluation>  $scored
     * @param  Collection<int, AssignmentEvaluation>  $incomplete
     */
    public function aggregate(
        int $driverId,
        Collection $latestFinalized,
        Collection $scored,
        Collection $incomplete,
    ): DriverPerformanceResult {
        $cfg = config('driver_evaluation.aggregation', []);
        $version = (string) ($cfg['version'] ?? '1.0.0');
        $minEstablished = (int) ($cfg['min_established_samples'] ?? 5);
        $window = (int) ($cfg['trend']['window_size'] ?? 5);
        $improvingDelta = (float) ($cfg['trend']['improving_delta'] ?? 3.0);
        $decliningDelta = (float) ($cfg['trend']['declining_delta'] ?? -3.0);

        $ordered = $scored
            ->sortBy(static function (AssignmentEvaluation $e): string {
                $ts = $e->finalized_at?->getTimestamp() ?? 0;

                return sprintf('%020d:%020d', $ts, (int) $e->id);
            })
            ->values();

        $scores = $ordered
            ->map(static fn (AssignmentEvaluation $e) => (float) $e->overall_score)
            ->all();

        $count = count($scores);
        $overall = $count === 0 ? null : $this->clamp(round(array_sum($scores) / $count, 2));

        $maturity = match (true) {
            $count === 0 => DriverPerformanceMaturity::UNAVAILABLE,
            $count < $minEstablished => DriverPerformanceMaturity::PROVISIONAL,
            default => DriverPerformanceMaturity::ESTABLISHED,
        };

        [$trend, $recent, $previous, $delta] = $this->resolveTrend(
            $scores,
            $window,
            $improvingDelta,
            $decliningDelta,
        );

        $categories = $this->aggregateCategories($ordered);
        $confidence = $this->resolveConfidence($ordered, $maturity, $count, $minEstablished);
        $label = $overall === null ? null : $this->labelFromScore($overall);
        $late = $this->countLateDeliveries($ordered);
        $lastEvaluated = $ordered->last()?->finalized_at?->toIso8601String()
            ?? $incomplete->sortBy('finalized_at')->last()?->finalized_at?->toIso8601String();

        $scoringVersions = $ordered
            ->pluck('scoring_version')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $evidenceSummary = $this->evidenceSummary($latestFinalized);

        return new DriverPerformanceResult(
            overallScore: $overall,
            scoreLabel: $label,
            confidence: $confidence,
            maturity: $maturity,
            trend: $trend,
            recentScore: $recent,
            previousScore: $previous,
            trendDelta: $delta,
            evaluatedAssignmentsCount: $count,
            incompleteAssignmentsCount: $incomplete->count(),
            totalDeliveries: $count,
            lateDeliveries: $late,
            categoryScores: $categories,
            aggregationVersion: $version,
            metadata: [
                'aggregation_code' => (string) ($cfg['code'] ?? 'eval.driver_agg'),
                'aggregation_version' => $version,
                'strategy' => (string) ($cfg['strategy'] ?? 'equal_weight_with_trend'),
                'live_only' => (bool) ($cfg['live_only'] ?? true),
                'min_established_samples' => $minEstablished,
                'trend_window_size' => $window,
                'trend_thresholds' => [
                    'improving_delta' => $improvingDelta,
                    'declining_delta' => $decliningDelta,
                ],
                'driver_id' => $driverId,
                'scoring_versions_seen' => $scoringVersions,
                'evidence_quality_summary' => $evidenceSummary,
                'eligible_evaluation_ids' => $ordered->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'incomplete_evaluation_ids' => $incomplete->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'superseded_excluded' => true,
                'invalidated_excluded' => true,
                'confidence_role' => 'reporting_dimension_not_numeric_weight',
            ],
            evaluationIds: $ordered->pluck('id')->map(fn ($id) => (int) $id)->all(),
            lastEvaluatedAt: $lastEvaluated,
        );
    }

    /**
     * @param  list<float>  $scores chronological
     * @return array{0: DriverPerformanceTrend, 1: float|null, 2: float|null, 3: float|null}
     */
    private function resolveTrend(
        array $scores,
        int $window,
        float $improvingDelta,
        float $decliningDelta,
    ): array {
        if (count($scores) < ($window * 2)) {
            $recentOnly = $scores === [] ? null : $this->clamp(round(array_sum($scores) / count($scores), 2));

            return [DriverPerformanceTrend::INSUFFICIENT_DATA, $recentOnly, null, null];
        }

        $recentSlice = array_slice($scores, -$window);
        $previousSlice = array_slice($scores, -($window * 2), $window);
        $recentAvg = round(array_sum($recentSlice) / count($recentSlice), 2);
        $previousAvg = round(array_sum($previousSlice) / count($previousSlice), 2);
        $delta = round($recentAvg - $previousAvg, 2);

        $trend = match (true) {
            $delta >= $improvingDelta => DriverPerformanceTrend::IMPROVING,
            $delta <= $decliningDelta => DriverPerformanceTrend::DECLINING,
            default => DriverPerformanceTrend::STABLE,
        };

        return [$trend, $this->clamp($recentAvg), $this->clamp($previousAvg), $delta];
    }

    /**
     * @param  Collection<int, AssignmentEvaluation>  $scored
     * @return array<string, array{score: float|null, sample_count: int, evaluable_sample_count: int}>
     */
    private function aggregateCategories(Collection $scored): array
    {
        $out = [];
        foreach (DriverEvaluationScoringCategory::cases() as $category) {
            $key = $category->value;
            $values = [];
            foreach ($scored as $evaluation) {
                $row = $evaluation->category_scores[$key] ?? null;
                if (! is_array($row)) {
                    continue;
                }
                if (! ($row['evaluable'] ?? false)) {
                    continue;
                }
                if ($row['score'] === null) {
                    continue;
                }
                $values[] = (float) $row['score'];
            }

            $sample = count($values);
            $out[$key] = [
                'score' => $sample === 0 ? null : $this->clamp(round(array_sum($values) / $sample, 2)),
                'sample_count' => $sample,
                'evaluable_sample_count' => $sample,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, AssignmentEvaluation>  $scored
     */
    private function resolveConfidence(
        Collection $scored,
        DriverPerformanceMaturity $maturity,
        int $count,
        int $minEstablished,
    ): AssignmentEvaluationConfidence {
        if ($maturity === DriverPerformanceMaturity::UNAVAILABLE || $count === 0) {
            return AssignmentEvaluationConfidence::LOW;
        }

        $high = 0;
        $medium = 0;
        $low = 0;
        foreach ($scored as $evaluation) {
            match ($evaluation->confidence) {
                AssignmentEvaluationConfidence::HIGH => $high++,
                AssignmentEvaluationConfidence::MEDIUM => $medium++,
                default => $low++,
            };
        }

        if ($maturity === DriverPerformanceMaturity::PROVISIONAL) {
            return $count === 1 && $high === 1
                ? AssignmentEvaluationConfidence::MEDIUM
                : AssignmentEvaluationConfidence::LOW;
        }

        // Established: majority high → HIGH; else if high+medium dominate → MEDIUM.
        if ($high >= ($minEstablished / 2) && $high >= $medium && $high >= $low) {
            return AssignmentEvaluationConfidence::HIGH;
        }
        if (($high + $medium) >= $low) {
            return AssignmentEvaluationConfidence::MEDIUM;
        }

        return AssignmentEvaluationConfidence::LOW;
    }

    /**
     * @param  Collection<int, AssignmentEvaluation>  $scored
     */
    private function countLateDeliveries(Collection $scored): int
    {
        $late = 0;
        foreach ($scored as $evaluation) {
            $contributions = $evaluation->category_scores['timeliness']['event_contributions'] ?? [];
            if (! is_array($contributions)) {
                continue;
            }
            foreach ($contributions as $contribution) {
                $code = $contribution['event_code'] ?? null;
                if (in_array($code, ['late_arrival', 'late_departure'], true)) {
                    $late++;
                    break;
                }
            }
        }

        return $late;
    }

    /**
     * @param  Collection<int, AssignmentEvaluation>  $latest
     * @return array<string, int>
     */
    private function evidenceSummary(Collection $latest): array
    {
        $summary = [
            AssignmentEvaluationEvidenceQuality::SUFFICIENT->value => 0,
            AssignmentEvaluationEvidenceQuality::PARTIAL->value => 0,
            AssignmentEvaluationEvidenceQuality::INSUFFICIENT->value => 0,
            AssignmentEvaluationEvidenceQuality::AMBIGUOUS->value => 0,
            'null' => 0,
        ];

        foreach ($latest as $evaluation) {
            $key = $evaluation->evidence_quality?->value ?? 'null';
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        return $summary;
    }

    private function labelFromScore(float $score): DriverEvaluationScoreLabel
    {
        $bands = config('driver_evaluation.aggregation.labels')
            ?? config('driver_evaluation.scoring.labels', []);

        if ($score >= (float) ($bands['excellent'] ?? 90)) {
            return DriverEvaluationScoreLabel::EXCELLENT;
        }
        if ($score >= (float) ($bands['good'] ?? 80)) {
            return DriverEvaluationScoreLabel::GOOD;
        }
        if ($score >= (float) ($bands['acceptable'] ?? 70)) {
            return DriverEvaluationScoreLabel::ACCEPTABLE;
        }
        if ($score >= (float) ($bands['needs_improvement'] ?? 60)) {
            return DriverEvaluationScoreLabel::NEEDS_IMPROVEMENT;
        }

        return DriverEvaluationScoreLabel::POOR;
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
