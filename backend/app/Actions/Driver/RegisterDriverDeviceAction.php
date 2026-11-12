<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\DriverDevice;
use App\Models\User;

/**
 * Idempotent Expo token registration (D12). Identity from linked driver only.
 */
class RegisterDriverDeviceAction
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
        $platform = isset($input['platform']) && is_string($input['platform'])
            ? strtolower(trim($input['platform']))
            : '';
        $deviceId = isset($input['device_id']) && is_string($input['device_id'])
            ? trim($input['device_id'])
            : null;
        if ($deviceId === '') {
            $deviceId = null;
        }

        if ($token === '' || strlen($token) > 255) {
            return ApiResult::validation(['push_token' => ['A valid push token is required.']]);
        }

        if (! in_array($platform, ['android', 'ios'], true)) {
            return ApiResult::validation(['platform' => ['Platform must be android or ios.']]);
        }

        $device = DriverDevice::query()->where('push_token', $token)->first();

        if ($device === null) {
            $device = DriverDevice::query()->create([
                'driver_id' => $driver->id,
                'platform' => $platform,
                'push_token' => $token,
                'device_id' => $deviceId,
                'is_active' => true,
                'last_seen_at' => now(),
                'invalidated_at' => null,
            ]);
        } else {
            $device->fill([
                'driver_id' => $driver->id,
                'platform' => $platform,
                'device_id' => $deviceId ?? $device->device_id,
                'is_active' => true,
                'last_seen_at' => now(),
                'invalidated_at' => null,
            ]);
            $device->save();
        }

        return ApiResult::success($device, 'Device registered');
    }
}
