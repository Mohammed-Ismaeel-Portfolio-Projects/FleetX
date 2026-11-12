<?php

namespace App\Actions\Notification;

use App\Core\ApiResult;
use App\Models\Notification;
use App\Models\User;

/**
 * Mark all unread notifications for authenticated user (C2.8-C). Never company-wide.
 */
class MarkAllClientNotificationsReadAction
{
    public function execute(User $user): ApiResult
    {
        if ($user->company_id === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $marked = Notification::query()
            ->ownedBy((int) $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return ApiResult::success(
            ['marked_read' => $marked],
            'Notifications marked as read'
        );
    }
}
