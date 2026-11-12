<?php

declare(strict_types=1);

namespace App\Actions\DriverEvaluation;

use App\Core\ApiResult;
use App\Enums\AssignmentEvaluationStatus;
use App\Models\AssignmentEvaluation;
use App\Models\Driver;
use App\Models\User;

/**
 * Read-only evaluation detail with events (DRIVER-EVAL-06).
 * Does not rescore or aggregate.
 */
class GetDriverEvaluationDetailAction
{
    public function executeForOps(User $user, Driver $driver, int $evaluationId): ApiResult
    {
        if (! $user->can('show', $driver)) {
            return ApiResult::error('Forbidden', 403);
        }

        $evaluation = $this->findEligible($evaluationId);
        if ($evaluation === null || (int) $evaluation->driver_id !== (int) $driver->id) {
            return ApiResult::error('Evaluation not found.', 404);
        }

        if (! $user->can('view', $evaluation)) {
            return ApiResult::error('Forbidden', 403);
        }

        return $this->success($evaluation);
    }

    public function executeForSelf(User $user, int $evaluationId): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $evaluation = $this->findEligible($evaluationId);
        if ($evaluation === null || (int) $evaluation->driver_id !== (int) $driver->id) {
            // IDOR: do not confirm existence of another driver's evaluation.
            return ApiResult::error('Evaluation not found.', 404);
        }

        if (! $user->can('view', $evaluation)) {
            return ApiResult::error('Evaluation not found.', 404);
        }

        return $this->success($evaluation);
    }

    private function findEligible(int $evaluationId): ?AssignmentEvaluation
    {
        return AssignmentEvaluation::query()
            ->whereKey($evaluationId)
            ->whereIn('status', [
                AssignmentEvaluationStatus::COMPLETED->value,
                AssignmentEvaluationStatus::INCOMPLETE->value,
            ])
            ->whereNotNull('finalized_at')
            ->with([
                'events',
                'assignment:id,assignment_code,is_active',
                'shipment:id,shipment_number,pickup_city,delivery_city',
            ])
            ->first();
    }

    private function success(AssignmentEvaluation $evaluation): ApiResult
    {
        return ApiResult::success($evaluation, 'Evaluation detail loaded');
    }
}
