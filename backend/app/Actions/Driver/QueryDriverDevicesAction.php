<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\DriverDevice;
use App\Models\User;

/**
 * List the authenticated driver's devices (D12). No raw tokens.
 */
class QueryDriverDevicesAction
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

        $devices = DriverDevice::query()
            ->forDriver($driver->id)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        return ApiResult::success($devices, 'Devices loaded');
    }
}
