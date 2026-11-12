<?php

namespace App\Http\Controllers\Client;

use App\Actions\Notification\CountClientUnreadNotificationsAction;
use App\Actions\Notification\MarkAllClientNotificationsReadAction;
use App\Actions\Notification\MarkClientNotificationReadAction;
use App\Actions\Notification\QueryClientNotificationsAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\MarkAllClientNotificationsReadRequest;
use App\Http\Requests\Client\MarkClientNotificationReadRequest;
use App\Http\Requests\Client\QueryClientNotificationsRequest;
use App\Http\Requests\Client\UnreadClientNotificationsCountRequest;
use App\Http\Resources\Client\ClientNotificationListCollection;
use App\Http\Resources\Client\ClientNotificationResource;
use Illuminate\Http\JsonResponse;

/**
 * Client-facing notification inbox (C2.8-C).
 * Distinct from Issues/Alerts and messaging markRead.
 */
class ClientNotificationController extends Controller
{
    public function index(
        QueryClientNotificationsRequest $request,
        QueryClientNotificationsAction $action
    ): JsonResponse {
        $this->authorize('client.notification.viewAny');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientNotificationListCollection($result->data),
            $result->message
        );
    }

    public function unreadCount(
        UnreadClientNotificationsCountRequest $request,
        CountClientUnreadNotificationsAction $action
    ): JsonResponse {
        $this->authorize('client.notification.viewAny');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function markRead(
        MarkClientNotificationReadRequest $request,
        string $notification,
        MarkClientNotificationReadAction $action
    ): JsonResponse {
        $this->authorize('client.notification.update');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $notification);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientNotificationResource($result->data),
            $result->message
        );
    }

    public function markAllRead(
        MarkAllClientNotificationsReadRequest $request,
        MarkAllClientNotificationsReadAction $action
    ): JsonResponse {
        $this->authorize('client.notification.update');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
