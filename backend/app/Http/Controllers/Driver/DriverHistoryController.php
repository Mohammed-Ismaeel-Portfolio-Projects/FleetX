<?php

namespace App\Http\Controllers\Driver;

use App\Actions\Driver\GetDriverAssignmentSummaryAction;
use App\Actions\Driver\QueryDriverAssignmentHistoryAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\QueryDriverAssignmentHistoryRequest;
use App\Http\Requests\Driver\ShowDriverAssignmentSummaryRequest;
use App\Http\Resources\Driver\DriverAssignmentHistoryItemResource;
use App\Http\Resources\Driver\DriverAssignmentSummaryResource;
use Illuminate\Http\JsonResponse;

/**
 * Driver historical assignment read surface (D11). Distinct from Ops assignment APIs.
 */
class DriverHistoryController extends Controller
{
    public function index(
        QueryDriverAssignmentHistoryRequest $request,
        QueryDriverAssignmentHistoryAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $page = (int) ($request->validated('page') ?? 1);
        $perPage = (int) ($request->validated('per_page') ?? 20);
        $status = $request->validated('status');

        $result = $action->execute($request->user(), $page, $perPage, $status);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        $paginator = $result->data;

        return ApiResponse::success([
            'data' => DriverAssignmentHistoryItemResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $result->message);
    }

    public function summary(
        ShowDriverAssignmentSummaryRequest $request,
        string $assignment,
        GetDriverAssignmentSummaryAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user(), $assignment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            new DriverAssignmentSummaryResource($result->data),
            $result->message
        );
    }
}
