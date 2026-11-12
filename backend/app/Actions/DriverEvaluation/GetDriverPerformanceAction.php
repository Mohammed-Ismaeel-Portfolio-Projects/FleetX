<?php

declare(strict_types=1);

namespace App\Actions\DriverEvaluation;

use App\Core\ApiResult;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\User;

/**
 * Read-only driver performance summary from driver_scores (DRIVER-EVAL-06).
 * Does not aggregate or score.
 */
class GetDriverPerformanceAction
{
    public function executeForOps(User $user, Driver $driver): ApiResult
    {
        if (! $user->can('show', $driver)) {
            return ApiResult::error('Forbidden', 403);
        }

        return $this->success($driver);
    }

    public function executeForSelf(User $user): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        return $this->success($driver);
    }

    private function success(Driver $driver): ApiResult
    {
        $projection = DriverScore::query()->where('driver_id', $driver->id)->first();

        return ApiResult::success([
            'driver' => $driver,
            'projection' => $projection,
        ], 'Driver performance loaded');
    }
}
