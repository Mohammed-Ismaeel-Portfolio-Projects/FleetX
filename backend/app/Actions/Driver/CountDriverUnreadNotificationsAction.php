<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\DriverNotification;
use App\Models\User;

class CountDriverUnreadNotificationsAction
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

        $count = DriverNotification::query()
            ->forDriver($driver->id)
            ->unread()
            ->count();

        return ApiResult::success(['unread_count' => $count], 'Unread count loaded');
    }
}
