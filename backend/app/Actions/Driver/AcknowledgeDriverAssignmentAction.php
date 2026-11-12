<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Status;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use Illuminate\Support\Facades\DB;

/**
 * Driver acknowledge: Assigned → Ready (D3).
 * Idempotent when already Ready. Ownership from linked Driver only.
 */
class AcknowledgeDriverAssignmentAction
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

        return DB::transaction(function () use ($user, $driver, $assignmentId) {
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

            // Idempotent: already Ready
            if ($statusName === DriverAssignmentTransitionMap::READY) {
                $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

                return ApiResult::success($assignment, 'Assignment already acknowledged');
            }

            if ($statusName !== DriverAssignmentTransitionMap::ASSIGNED) {
                return ApiResult::error(
                    'Assignment cannot be acknowledged in its current state.',
                    409
                );
            }

            $readyStatus = Status::query()
                ->where('name', DriverAssignmentTransitionMap::READY)
                ->where('type', 'assignment')
                ->first();

            if ($readyStatus === null) {
                return ApiResult::error('Ready assignment status is not configured.', 500);
            }

            $assignment->update([
                'status_id' => $readyStatus->id,
                'updated_by' => $user->id,
            ]);

            $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

            return ApiResult::success($assignment, 'Assignment acknowledged successfully');
        });
    }
}
