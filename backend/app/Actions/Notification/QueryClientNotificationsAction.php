<?php

namespace App\Actions\Notification;

use App\Core\ApiResult;
use App\Models\Notification;
use App\Models\User;

/**
 * Paginated inbox for authenticated client (C2.8-C).
 * Ownership from auth user id only — never from request input.
 */
class QueryClientNotificationsAction
{
    public function execute(User $user, array $params): ApiResult
    {
        if ($user->company_id === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $query = Notification::query()
            ->ownedBy((int) $user->id);

        if (array_key_exists('is_read', $params) && $params['is_read'] !== null) {
            $query->where('is_read', (bool) $params['is_read']);
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        $notifications = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($notifications, 'Notifications retrieved successfully');
    }
}
