<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\DriverNotification;
use App\Models\User;

class MarkAllDriverNotificationsReadAction
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

        $updated = DriverNotification::query()
            ->forDriver($driver->id)
            ->unread()
            ->update(['read_at' => now()]);

        return ApiResult::success(['marked' => $updated], 'All notifications marked read');
    }
}
