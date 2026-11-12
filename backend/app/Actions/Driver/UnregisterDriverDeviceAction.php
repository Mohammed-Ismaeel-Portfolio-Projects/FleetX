<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\DriverDevice;
use App\Models\User;

/**
 * Deactivate the authenticated driver's token (D12). Idempotent.
 */
class UnregisterDriverDeviceAction
{
    public function execute(User $user, array $input): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $token = isset($input['push_token']) && is_string($input['push_token'])
            ? trim($input['push_token'])
            : '';

        if ($token === '') {
            return ApiResult::validation(['push_token' => ['A valid push token is required.']]);
        }

        $device = DriverDevice::query()
            ->forDriver($driver->id)
            ->where('push_token', $token)
            ->first();

        if ($device === null) {
            return ApiResult::success(['unregistered' => false], 'Device not registered');
        }

        $device->is_active = false;
        $device->invalidated_at = now();
        $device->save();

        return ApiResult::success($device, 'Device unregistered');
    }
}
