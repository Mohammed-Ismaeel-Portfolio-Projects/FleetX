<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\User;
use App\Support\DriverSelfServiceAvailability;

/**
 * Read the authenticated driver's self-service availability (D13).
 */
class GetDriverSelfAvailabilityAction
{
    public function execute(User $user): ApiResult
    {
        $user->loadMissing(['role', 'driver.status', 'driver.activeAssignment']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $snapshot = DriverSelfServiceAvailability::snapshot(
            $driver->status?->name,
            (bool) $driver->is_active,
            $driver->activeAssignment !== null
        );

        return ApiResult::success($snapshot, 'Availability loaded');
    }
}
