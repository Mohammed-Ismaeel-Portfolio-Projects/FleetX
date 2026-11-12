<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DriverEvaluation\GetDriverEvaluationDetailAction;
use App\Actions\DriverEvaluation\GetDriverPerformanceAction;
use App\Actions\DriverEvaluation\QueryDriverEvaluationsAction;
use App\Actions\DriverEvaluation\QueryDriverPerformanceSummariesAction;
use App\Core\ApiResponse;
use App\Http\Resources\DriverEvaluation\AssignmentEvaluationDetailResource;
use App\Http\Resources\DriverEvaluation\AssignmentEvaluationSummaryResource;
use App\Http\Resources\DriverEvaluation\DriverPerformanceCompactResource;
use App\Http\Resources\DriverEvaluation\DriverPerformanceResource;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ops (Admin/Dispatcher) driver performance read APIs (DRIVER-EVAL-06 / 08A).
 */
class DriverPerformanceController extends Controller
{
    /**
     * Batch compact overall performance (DRIVER-EVAL-08A).
     * Query: ?ids=1,2,3 — max 100. Single projection query; no scoring.
     */
    public function summary(Request $request, QueryDriverPerformanceSummariesAction $action): JsonResponse
    {
        $raw = (string) $request->query('ids', '');
        $ids = $raw === '' ? [] : (preg_split('/\s*,\s*/', $raw) ?: []);

        $result = $action->executeForOps($request->user(), $ids);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var \Illuminate\Support\Collection<int, array{driver_id: int, projection: \App\Models\DriverScore|null}> $items */
        $items = $result->data;

        $data = $items->map(function (array $row) {
            $compact = (new DriverPerformanceCompactResource($row['projection']))->resolve();
            $compact['driver_id'] = $row['driver_id'];

            return $compact;
        })->values()->all();

        return ApiResponse::success(['data' => $data], $result->message);
    }

    public function show(Request $request, string $driver, GetDriverPerformanceAction $action): JsonResponse
    {
        $model = Driver::query()->findOrFail($driver);
        $this->authorize('show', $model);

        $result = $action->executeForOps($request->user(), $model);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{driver: Driver, projection: \App\Models\DriverScore|null} $payload */
        $payload = $result->data;
        $data = (new DriverPerformanceResource($payload['projection']))->resolve();
        $data['driver_id'] = (int) $payload['driver']->id;

        return ApiResponse::success($data, $result->message);
    }

    public function evaluations(
        Request $request,
        string $driver,
        QueryDriverEvaluationsAction $action,
    ): JsonResponse {
        $model = Driver::query()->findOrFail($driver);
        $this->authorize('show', $model);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $result = $action->executeForOps($request->user(), $model, $page, $perPage);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        $paginator = $result->data;

        return ApiResponse::success([
            'data' => AssignmentEvaluationSummaryResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $result->message);
    }

    public function evaluation(
        Request $request,
        string $driver,
        string $evaluation,
        GetDriverEvaluationDetailAction $action,
    ): JsonResponse {
        $model = Driver::query()->findOrFail($driver);
        $this->authorize('show', $model);

        $result = $action->executeForOps($request->user(), $model, (int) $evaluation);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            (new AssignmentEvaluationDetailResource($result->data))->resolve(),
            $result->message
        );
    }
}
