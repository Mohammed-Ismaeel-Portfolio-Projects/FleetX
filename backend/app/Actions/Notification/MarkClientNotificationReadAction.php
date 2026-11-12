<?php

namespace App\Actions\Notification;

use App\Core\ApiResult;
use App\Models\Notification;
use App\Models\User;

/**
 * Mark one owned notification as read (C2.8-C). Scoped lookup — foreign/unknown → 404.
 */
class MarkClientNotificationReadAction
{
    public function execute(User $user, string $notificationId): ApiResult
    {
        if ($user->company_id === null) {
            return ApiResult::error('Forbidden', 403);
        }

        if (! ctype_digit($notificationId)) {
            return ApiResult::notFound('Notification not found');
        }

        $notification = Notification::query()
            ->ownedBy((int) $user->id)
            ->whereKey((int) $notificationId)
            ->first();

        if ($notification === null) {
            return ApiResult::notFound('Notification not found');
        }

        if (! $notification->is_read) {
            $notification->is_read = true;
            $notification->save();
        }

        return ApiResult::success($notification->fresh(), 'Notification marked as read');
    }
}
