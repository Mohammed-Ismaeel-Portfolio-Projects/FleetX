<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Status;
use App\Models\User;
use App\Services\DriverEvaluation\DriverEvaluationLifecycleService;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use Illuminate\Support\Facades\DB;

/**
 * Driver start operation: Ready → In Operation (D3).
 * Idempotent when already In Operation. Ownership from linked Driver only.
 * DRIVER-EVAL-07: opens mutable LIVE evaluation context after success (non-blocking).
 */
class StartDriverAssignmentAction
{
    public function execute(User $user, int|string $assignmentId): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $result = DB::transaction(function () use ($user, $driver, $assignmentId) {
            /** @var Assignment|null $assignment */
            $assignment = Assignment::query()
                ->forDriver($driver->id)
                ->lockForUpdate()
                ->find($assignmentId);

            if ($assignment === null) {
                return ApiResult::notFound('Assignment not found');
            }

            $assignment->loadMissing('status');
            $statusName = $assignment->status?->name;

            if (! $assignment->is_active || DriverAssignmentTransitionMap::isTerminal($statusName)) {
                return ApiResult::error('Assignment is not active or has ended.', 409);
            }

            // Idempotent: already In Operation — ensure phase exists for D4 resume
            if ($statusName === DriverAssignmentTransitionMap::IN_OPERATION) {
                if ($assignment->execution_phase === null) {
                    $assignment->update([
                        'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
                        'updated_by' => $user->id,
                    ]);
                }
                $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

                return ApiResult::success($assignment, 'Assignment already in operation');
            }

            if ($statusName !== DriverAssignmentTransitionMap::READY) {
                return ApiResult::error(
                    'Assignment must be acknowledged (Ready) before starting.',
                    409
                );
            }

            $inOperationStatus = Status::query()
                ->where('name', DriverAssignmentTransitionMap::IN_OPERATION)
                ->where('type', 'assignment')
                ->first();

            if ($inOperationStatus === null) {
                return ApiResult::error('In Operation assignment status is not configured.', 500);
            }

            $assignment->update([
                'status_id' => $inOperationStatus->id,
                'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
                'updated_by' => $user->id,
            ]);

            $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

            return ApiResult::success($assignment, 'Assignment started successfully');
        });

        if ($result->success && $result->data instanceof Assignment) {
            app(DriverEvaluationLifecycleService::class)->openForAssignmentSafely($result->data);
        }

        return $result;
    }
}
