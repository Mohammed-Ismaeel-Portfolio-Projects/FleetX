<?php

declare(strict_types=1);

namespace App\Services\DriverEvaluation\Aggregation;

use App\Enums\AssignmentEvaluationStatus;
use App\Models\AssignmentEvaluation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Selects authoritative finalized evaluations for driver aggregation (DRIVER-EVAL-05).
 *
 * Rules:
 * - Uses evaluation.driver_id snapshot (never current Assignment.driver_id).
 * - Latest revision per assignment among finalized non-invalidated rows.
 * - PENDING / PROCESSING / INVALIDATED excluded.
 * - INCOMPLETE counted separately; never treated as a numeric penalty.
 * - COMPLETED with overall_score contributes to the score set.
 */
final class EligibleEvaluationSelector
{
    /**
     * Latest valid revision rows (COMPLETED or INCOMPLETE) for a driver.
     *
     * @return Collection<int, AssignmentEvaluation>
     */
    public function latestFinalizedForDriver(int $driverId): Collection
    {
        $liveOnly = (bool) config('driver_evaluation.aggregation.live_only', true);

        $latest = AssignmentEvaluation::query()
            ->from('assignment_evaluations as ae')
            ->joinSub(
                AssignmentEvaluation::query()
                    ->select('assignment_id', DB::raw('MAX(revision) as max_revision'))
                    ->where('driver_id', $driverId)
                    ->whereIn('status', [
                        AssignmentEvaluationStatus::COMPLETED->value,
                        AssignmentEvaluationStatus::INCOMPLETE->value,
                    ])
                    ->whereNotNull('finalized_at')
                    ->when($liveOnly, static fn ($q) => $q->where('evaluation_mode', 'live'))
                    ->groupBy('assignment_id'),
                'latest',
                function ($join) {
                    $join->on('ae.assignment_id', '=', 'latest.assignment_id')
                        ->on('ae.revision', '=', 'latest.max_revision');
                },
            )
            ->where('ae.driver_id', $driverId)
            ->whereIn('ae.status', [
                AssignmentEvaluationStatus::COMPLETED->value,
                AssignmentEvaluationStatus::INCOMPLETE->value,
            ])
            ->whereNotNull('ae.finalized_at')
            ->when($liveOnly, static fn ($q) => $q->where('ae.evaluation_mode', 'live'))
            ->select('ae.*')
            ->orderBy('ae.finalized_at')
            ->orderBy('ae.id')
            ->get();

        return $latest;
    }

    /**
     * Scored subset: COMPLETED with a persisted overall_score.
     *
     * @param  Collection<int, AssignmentEvaluation>  $latest
     * @return Collection<int, AssignmentEvaluation>
     */
    public function scoredOnly(Collection $latest): Collection
    {
        return $latest
            ->filter(static function (AssignmentEvaluation $evaluation) {
                return $evaluation->status === AssignmentEvaluationStatus::COMPLETED
                    && $evaluation->overall_score !== null;
            })
            ->values();
    }

    /**
     * Incomplete subset (latest revision incomplete).
     *
     * @param  Collection<int, AssignmentEvaluation>  $latest
     * @return Collection<int, AssignmentEvaluation>
     */
    public function incompleteOnly(Collection $latest): Collection
    {
        return $latest
            ->filter(static fn (AssignmentEvaluation $e) => $e->status === AssignmentEvaluationStatus::INCOMPLETE)
            ->values();
    }
}
