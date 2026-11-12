<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\DriverNotification;
use App\Models\User;

class QueryDriverNotificationsAction
{
    public function execute(User $user, int $page = 1, int $perPage = 20): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        $paginator = DriverNotification::query()
            ->forDriver($driver->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($paginator, 'Notifications loaded');
    }
}
