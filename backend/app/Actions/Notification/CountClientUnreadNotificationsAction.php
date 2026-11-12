<?php

namespace App\Actions\Notification;

use App\Core\ApiResult;
use App\Models\Notification;
use App\Models\User;

/**
 * Unread count for authenticated client only (C2.8-C).
 */
class CountClientUnreadNotificationsAction
{
    public function execute(User $user): ApiResult
    {
        if ($user->company_id === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $count = Notification::query()
            ->ownedBy((int) $user->id)
            ->where('is_read', false)
            ->count();

        return ApiResult::success(
            ['unread_count' => $count],
            'Unread count retrieved successfully'
        );
    }
}
