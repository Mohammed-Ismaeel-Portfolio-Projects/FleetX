<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\User;

/**
 * Resolve the authenticated driver's single active assignment (D2).
 * Ownership is derived from User → linked Driver — never from request input.
 */
class GetDriverActiveAssignmentAction
{
    public function execute(User $user): ApiResult
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
            ->active()
            ->forDriver($driver->id)
            ->with([
                'status',
                'shipment.status',
                'vehicle',
                'trailer',
            ])
            ->first();

        return ApiResult::success($assignment, $assignment
            ? 'Active assignment retrieved successfully'
            : 'No active assignment');
    }
}
