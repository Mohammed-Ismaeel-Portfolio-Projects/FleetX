<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\DriverNotification;
use App\Models\User;

class MarkDriverNotificationReadAction
{
    public function execute(User $user, int|string $notificationId): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $notification = DriverNotification::query()
            ->forDriver($driver->id)
            ->find($notificationId);

        if ($notification === null) {
            return ApiResult::notFound('Notification not found');
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResult::success($notification->fresh(), 'Notification marked read');
    }
}
