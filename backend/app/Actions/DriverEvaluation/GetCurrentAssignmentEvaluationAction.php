<?php

declare(strict_types=1);

namespace App\Actions\DriverEvaluation;

use App\Core\ApiResult;
use App\Enums\AssignmentEvaluationMode;
use App\Models\Assignment;
use App\Models\AssignmentEvaluation;
use App\Models\User;
use App\Services\DriverEvaluation\CurrentAssignmentEvaluationReader;

/**
 * Read-only current AssignmentEvaluation for an Assignment (DRIVER-EVAL-08A).
 * Does not create, score, detect, aggregate, or load telemetry.
 */
class GetCurrentAssignmentEvaluationAction
{
    public function __construct(
        private readonly CurrentAssignmentEvaluationReader $reader,
    ) {}

    /**
     * Ops (Admin / Dispatcher): fleet evaluation visibility via driverEvaluation.viewAny.
     * Intentionally does NOT require assignment.view so Admin can read current eval
     * without widening operational assignment CRUD gates.
     */
    public function executeForOps(User $user, Assignment $assignment): ApiResult
    {
        if (! $user->can('viewAny', AssignmentEvaluation::class)) {
            return ApiResult::error('Forbidden', 403);
        }

        return $this->success($assignment);
    }

    /**
     * Driver self: linked driver must own the assignment.
     */
    public function executeForSelf(User $user, Assignment $assignment): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        if ((int) $assignment->driver_id !== (int) $driver->id) {
            // IDOR: do not confirm another driver's assignment evaluation.
            return ApiResult::error('Assignment not found.', 404);
        }

        return $this->success($assignment);
    }

    private function success(Assignment $assignment): ApiResult
    {
        $evaluation = $this->reader->resolve($assignment, AssignmentEvaluationMode::LIVE);

        if ($evaluation !== null) {
            $evaluation->load([
                'events' => fn ($q) => $q->orderByDesc('occurred_at'),
            ]);
        }

        return ApiResult::success([
            'assignment' => $assignment,
            'evaluation' => $evaluation,
        ], 'Current assignment evaluation loaded');
    }
}
