<?php

namespace App\Http\Controllers\Driver;

use App\Actions\Driver\CountDriverUnreadNotificationsAction;
use App\Actions\Driver\MarkAllDriverNotificationsReadAction;
use App\Actions\Driver\MarkDriverNotificationReadAction;
use App\Actions\Driver\QueryDriverNotificationsAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\DriverNotificationIndexRequest;
use App\Http\Requests\Driver\DriverNotificationMutationRequest;
use App\Http\Resources\Driver\DriverNotificationResource;
use Illuminate\Http\JsonResponse;

/**
 * Driver notification inbox (D8) — distinct from Client /notifications.
 */
class DriverNotificationController extends Controller
{
    public function index(
        DriverNotificationIndexRequest $request,
        QueryDriverNotificationsAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $page = (int) ($request->validated('page') ?? 1);
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $result = $action->execute($request->user(), $page, $perPage);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        $paginator = $result->data;

        return ApiResponse::success([
            'data' => DriverNotificationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $result->message);
    }

    public function unreadCount(
        DriverNotificationMutationRequest $request,
        CountDriverUnreadNotificationsAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function markRead(
        DriverNotificationMutationRequest $request,
        string $notification,
        MarkDriverNotificationReadAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user(), $notification);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            new DriverNotificationResource($result->data),
            $result->message
        );
    }

    public function markAllRead(
        DriverNotificationMutationRequest $request,
        MarkAllDriverNotificationsReadAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
