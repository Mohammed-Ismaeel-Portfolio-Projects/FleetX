<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Scoring;

use App\Enums\AssignmentEvaluationConfidence;
use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationScoreLabel;
use App\Enums\DriverEvaluationScoringCategory;
use App\Models\AssignmentEvaluationEvent;
use Illuminate\Support\Collection;

/**
 * Pure, deterministic scoring engine (DRIVER-EVAL-04).
 *
 * overall = Σ(category_score × renormalized_weight) for evaluable categories.
 * Missing evidence ⇒ NOT_EVALUABLE (renormalize), never an automatic penalty.
 */
final class DriverEvaluationScoringEngine
{
    public function __construct(
        private readonly EventOverlapResolver $overlapResolver,
    ) {}

    /**
     * @param  Collection<int, AssignmentEvaluationEvent>  $events
     */
    public function score(Collection $events, EvaluationScoringContext $context): AssignmentEvaluationScoreResult
    {
        $cfg = config('driver_evaluation.scoring');
        $baseline = (float) ($cfg['baseline'] ?? 100);
        $min = (float) ($cfg['min_score'] ?? 0);
        $max = (float) ($cfg['max_score'] ?? 100);
        $maxDeduction = (float) ($cfg['max_category_deduction'] ?? 100);
        $decay = (float) ($cfg['repetition_decay'] ?? 0.7);
        $impacts = $cfg['event_impacts'] ?? [];
        $weights = $cfg['categories'] ?? [];
        $scoringCode = (string) ($cfg['code'] ?? 'eval.score');
        $scoringVersion = (string) ($cfg['version'] ?? '1.0.0');
        $minTelemetry = (int) ($cfg['min_telemetry_samples'] ?? 3);

        $overlap = $this->overlapResolver->resolve($events);
        /** @var Collection<int, AssignmentEvaluationEvent> $scoredEvents */
        $scoredEvents = $overlap['scored'];

        $categories = [];
        foreach (DriverEvaluationScoringCategory::cases() as $category) {
            $configuredWeight = (float) ($weights[$category->value] ?? 0);
            $evaluable = $this->isCategoryEvaluable($category, $context, $scoredEvents);

            if (! $evaluable) {
                $categories[] = [
                    'key' => $category->value,
                    'evaluable' => false,
                    'score' => null,
                    'configured_weight' => $configuredWeight,
                    'normalized_weight' => null,
                    'event_contributions' => [],
                    'skipped_overlaps' => [],
                    'reason' => 'insufficient_evidence_for_category',
                ];
                continue;
            }

            [$score, $contributions] = $this->scoreCategory(
                $category,
                $scoredEvents,
                $impacts,
                $baseline,
                $min,
                $max,
                $maxDeduction,
                $decay,
            );

            $categories[] = [
                'key' => $category->value,
                'evaluable' => true,
                'score' => $score,
                'configured_weight' => $configuredWeight,
                'normalized_weight' => null, // filled after renormalization
                'event_contributions' => $contributions,
                'skipped_overlaps' => array_values(array_filter(
                    $overlap['skipped'],
                    static fn (array $row) => ($impacts[$row['event_code']] ?? null) !== null
                        && ($cfg['event_category_map'][$row['event_code']] ?? null) === $category->value
                )),
            ];
        }

        $evaluableWeightSum = 0.0;
        foreach ($categories as $category) {
            if ($category['evaluable']) {
                $evaluableWeightSum += (float) $category['configured_weight'];
            }
        }

        $overall = null;
        $isScoreAvailable = $evaluableWeightSum > 0.0;

        if ($isScoreAvailable) {
            $overall = 0.0;
            foreach ($categories as $i => $category) {
                if (! $category['evaluable']) {
                    continue;
                }
                $normalized = ((float) $category['configured_weight']) / $evaluableWeightSum;
                $categories[$i]['normalized_weight'] = round($normalized, 6);
                $overall += ((float) $category['score']) * $normalized;
            }
            $overall = $this->clamp(round($overall, 2), $min, $max);
        }

        $confidence = $this->resolveConfidence($context, $categories, $minTelemetry);
        $label = $overall === null ? null : DriverEvaluationScoreLabel::fromScore($overall);

        return new AssignmentEvaluationScoreResult(
            overallScore: $overall,
            scoreLabel: $label,
            confidence: $confidence,
            scoringCode: $scoringCode,
            scoringVersion: $scoringVersion,
            categories: $categories,
            metadata: [
                'scoring_code' => $scoringCode,
                'scoring_version' => $scoringVersion,
                'baseline' => $baseline,
                'configured_weights' => $weights,
                'evaluable_weight_sum' => round($evaluableWeightSum, 6),
                'event_impacts' => $impacts,
                'repetition_decay' => $decay,
                'overlap_groups' => $cfg['overlap_groups'] ?? [],
                'overlap_skipped' => $overlap['skipped'],
                'context' => [
                    'telemetry_sample_count' => $context->telemetrySampleCount,
                    'route_geometry_available' => $context->routeGeometryAvailable,
                    'timing_data_available' => $context->timingDataAvailable,
                    'workflow_data_available' => $context->workflowDataAvailable,
                ],
                'event_count' => $events->count(),
                'scored_event_count' => $scoredEvents->count(),
            ],
            isScoreAvailable: $isScoreAvailable,
        );
    }

    /**
     * @param  Collection<int, AssignmentEvaluationEvent>  $events
     */
    private function isCategoryEvaluable(
        DriverEvaluationScoringCategory $category,
        EvaluationScoringContext $context,
        Collection $events,
    ): bool {
        $minTelemetry = (int) config('driver_evaluation.scoring.min_telemetry_samples', 3);
        $hasCategoryEvent = $events->contains(
            function (AssignmentEvaluationEvent $event) use ($category) {
                $code = $event->event_code;
                if (! $code instanceof DriverEvaluationEventCode || ! $code->isCurrentlySupported()) {
                    return false;
                }

                return $this->overlapResolver->scoringCategoryFor($code) === $category;
            }
        );

        return match ($category) {
            DriverEvaluationScoringCategory::SAFETY,
            DriverEvaluationScoringCategory::DRIVING_BEHAVIOR => $context->telemetrySampleCount >= $minTelemetry || $hasCategoryEvent,
            DriverEvaluationScoringCategory::ROUTE_COMPLIANCE => $context->routeGeometryAvailable || $hasCategoryEvent,
            DriverEvaluationScoringCategory::TIMELINESS => $context->timingDataAvailable || $hasCategoryEvent,
            DriverEvaluationScoringCategory::WORKFLOW_DISCIPLINE => $context->workflowDataAvailable || $hasCategoryEvent,
        };
    }

    /**
     * @param  Collection<int, AssignmentEvaluationEvent>  $events
     * @param  array<string, float|int>  $impacts
     * @return array{0: float, 1: list<array<string, mixed>>}
     */
    private function scoreCategory(
        DriverEvaluationScoringCategory $category,
        Collection $events,
        array $impacts,
        float $baseline,
        float $min,
        float $max,
        float $maxDeduction,
        float $decay,
    ): array {
        $categoryEvents = $events
            ->filter(function (AssignmentEvaluationEvent $event) use ($category) {
                $code = $event->event_code;
                if (! $code instanceof DriverEvaluationEventCode || ! $code->isCurrentlySupported()) {
                    return false;
                }

                return $this->overlapResolver->scoringCategoryFor($code) === $category;
            })
            ->sortBy([
                fn (AssignmentEvaluationEvent $e) => $e->occurred_at?->timestamp ?? 0,
                fn (AssignmentEvaluationEvent $e) => $e->id,
            ])
            ->values();

        $occurrenceByCode = [];
        $deduction = 0.0;
        $contributions = [];

        foreach ($categoryEvents as $event) {
            /** @var DriverEvaluationEventCode $code */
            $code = $event->event_code;
            $baseImpact = (float) ($impacts[$code->value] ?? 0);
            if ($baseImpact <= 0) {
                continue;
            }

            $n = ($occurrenceByCode[$code->value] ?? 0) + 1;
            $occurrenceByCode[$code->value] = $n;
            $multiplier = $n === 1 ? 1.0 : ($decay ** ($n - 1));
            $applied = round($baseImpact * $multiplier, 4);
            $deduction += $applied;

            $contributions[] = [
                'event_id' => (int) $event->id,
                'event_code' => $code->value,
                'occurrence_index' => $n,
                'base_impact' => $baseImpact,
                'repetition_multiplier' => round($multiplier, 6),
                'applied_impact' => $applied,
            ];
        }

        $deduction = min($deduction, $maxDeduction);
        $score = $this->clamp($baseline - $deduction, $min, $max);

        return [round($score, 2), $contributions];
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     */
    private function resolveConfidence(
        EvaluationScoringContext $context,
        array $categories,
        int $minTelemetry,
    ): AssignmentEvaluationConfidence {
        $evaluable = count(array_filter($categories, static fn (array $c) => $c['evaluable']));
        $total = count($categories);

        if ($evaluable === 0) {
            return AssignmentEvaluationConfidence::LOW;
        }

        $hasTelemetry = $context->telemetrySampleCount >= $minTelemetry;
        $hasRoute = $context->routeGeometryAvailable;
        $hasTiming = $context->timingDataAvailable;

        if ($evaluable === $total && $hasTelemetry && $hasRoute && $hasTiming) {
            return AssignmentEvaluationConfidence::HIGH;
        }

        if ($evaluable >= 3 && ($hasTelemetry || $hasTiming)) {
            return AssignmentEvaluationConfidence::MEDIUM;
        }

        return AssignmentEvaluationConfidence::LOW;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
