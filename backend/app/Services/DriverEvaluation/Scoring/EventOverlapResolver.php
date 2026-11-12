<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Scoring;

use App\Enums\DriverEvaluationEventCode;
use App\Enums\DriverEvaluationScoringCategory;
use App\Models\AssignmentEvaluationEvent;
use Illuminate\Support\Collection;

/**
 * Deterministic overlap resolution for related events (DRIVER-EVAL-04).
 *
 * Rule: if a secondary event shares ≥1 tracking-log ID with a primary event
 * in the same configured overlap group, the secondary is skipped for scoring.
 * Timing events without log IDs: secondary abnormal_duration is skipped when
 * any late_arrival exists on the evaluation.
 */
final class EventOverlapResolver
{
    /**
     * @param  Collection<int, AssignmentEvaluationEvent>  $events
     * @return array{
     *   scored: Collection<int, AssignmentEvaluationEvent>,
     *   skipped: list<array{event_id: int, event_code: string, reason: string, primary_event_id?: int}>
     * }
     */
    public function resolve(Collection $events): array
    {
        /** @var list<array{primary: string, secondary: list<string>}> $groups */
        $groups = config('driver_evaluation.scoring.overlap_groups', []);
        $skipped = [];
        $skipIds = [];

        foreach ($groups as $group) {
            $primaryCode = (string) ($group['primary'] ?? '');
            $secondaryCodes = array_map('strval', $group['secondary'] ?? []);
            if ($primaryCode === '' || $secondaryCodes === []) {
                continue;
            }

            $primaries = $events->filter(
                static fn (AssignmentEvaluationEvent $e) => $e->event_code?->value === $primaryCode
            );

            foreach ($events as $event) {
                $code = $event->event_code?->value;
                if ($code === null || ! in_array($code, $secondaryCodes, true)) {
                    continue;
                }

                if ($this->shouldSkipSecondary($event, $primaries, $primaryCode)) {
                    $skipIds[] = (int) $event->id;
                    $matchedPrimary = $this->matchingPrimary($event, $primaries);
                    $skipped[] = [
                        'event_id' => (int) $event->id,
                        'event_code' => $code,
                        'reason' => 'overlap_with_primary',
                        'primary_event_id' => $matchedPrimary?->id,
                        'primary_event_code' => $primaryCode,
                    ];
                }
            }
        }

        $scored = $events->filter(
            static fn (AssignmentEvaluationEvent $e) => ! in_array((int) $e->id, $skipIds, true)
        )->values();

        return [
            'scored' => $scored,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  Collection<int, AssignmentEvaluationEvent>  $primaries
     */
    private function shouldSkipSecondary(
        AssignmentEvaluationEvent $secondary,
        Collection $primaries,
        string $primaryCode,
    ): bool {
        if ($primaries->isEmpty()) {
            return false;
        }

        $secondaryLogs = array_map('intval', $secondary->evidence_tracking_log_ids ?? []);

        // Timing-style events often have empty log refs: skip secondary when any primary exists.
        if ($secondaryLogs === [] && in_array($primaryCode, [
            DriverEvaluationEventCode::LATE_ARRIVAL->value,
        ], true)) {
            return true;
        }

        foreach ($primaries as $primary) {
            $primaryLogs = array_map('intval', $primary->evidence_tracking_log_ids ?? []);
            if ($secondaryLogs !== [] && $primaryLogs !== [] && array_intersect($secondaryLogs, $primaryLogs) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, AssignmentEvaluationEvent>  $primaries
     */
    private function matchingPrimary(AssignmentEvaluationEvent $secondary, Collection $primaries): ?AssignmentEvaluationEvent
    {
        $secondaryLogs = array_map('intval', $secondary->evidence_tracking_log_ids ?? []);
        foreach ($primaries as $primary) {
            $primaryLogs = array_map('intval', $primary->evidence_tracking_log_ids ?? []);
            if ($secondaryLogs === [] || array_intersect($secondaryLogs, $primaryLogs) !== []) {
                return $primary;
            }
        }

        return $primaries->first();
    }

    public function scoringCategoryFor(DriverEvaluationEventCode $code): ?DriverEvaluationScoringCategory
    {
        $map = config('driver_evaluation.scoring.event_category_map', []);
        $key = $map[$code->value] ?? null;
        if (! is_string($key)) {
            return null;
        }

        return DriverEvaluationScoringCategory::tryFrom($key);
    }
}
