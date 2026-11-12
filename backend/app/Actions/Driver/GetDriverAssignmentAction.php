<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\User;

/**
 * Driver-scoped assignment show (D2).
 * Soft-deleted / foreign / inactive-or-other-driver → 404 (no existence leak beyond ownership).
 */
class GetDriverAssignmentAction
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

        $assignment = Assignment::query()
            ->forDriver($driver->id)
            ->with([
                'status',
                'shipment.status',
                'vehicle',
                'trailer',
            ])
            ->find($assignmentId);

        if ($assignment === null) {
            return ApiResult::notFound('Assignment not found');
        }

        return ApiResult::success($assignment, 'Assignment retrieved successfully');
    }
}
