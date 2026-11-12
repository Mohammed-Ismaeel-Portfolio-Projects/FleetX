<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Actions\DriverEvaluation\GetCurrentAssignmentEvaluationAction;
use App\Actions\DriverEvaluation\GetDriverEvaluationDetailAction;
use App\Actions\DriverEvaluation\GetDriverPerformanceAction;
use App\Actions\DriverEvaluation\QueryDriverEvaluationsAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\DriverEvaluation\AssignmentEvaluationDetailResource;
use App\Http\Resources\DriverEvaluation\AssignmentEvaluationSummaryResource;
use App\Http\Resources\DriverEvaluation\CurrentAssignmentEvaluationResource;
use App\Http\Resources\DriverEvaluation\DriverPerformanceResource;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Driver self-service performance reads (DRIVER-EVAL-06 / 08A).
 * Identity always from authenticated linked driver — never from path/body driver_id.
 */
class DriverSelfPerformanceController extends Controller
{
    /**
     * Current assignment evaluation (DRIVER-EVAL-08A) — not overall driver_scores.
     */
    public function currentAssignmentEvaluation(
        Request $request,
        string $assignment,
        GetCurrentAssignmentEvaluationAction $action,
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $model = Assignment::query()->findOrFail($assignment);

        $result = $action->executeForSelf($request->user(), $model);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            (new CurrentAssignmentEvaluationResource($result->data))->resolve(),
            $result->message
        );
    }

    public function show(Request $request, GetDriverPerformanceAction $action): JsonResponse
    {
        $this->authorize('driver.work.view');

        $result = $action->executeForSelf($request->user());
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{driver: \App\Models\Driver, projection: \App\Models\DriverScore|null} $payload */
        $payload = $result->data;
        $data = (new DriverPerformanceResource($payload['projection']))->resolve();
        $data['driver_id'] = (int) $payload['driver']->id;

        return ApiResponse::success($data, $result->message);
    }

    public function evaluations(Request $request, QueryDriverEvaluationsAction $action): JsonResponse
    {
        $this->authorize('driver.work.view');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $result = $action->executeForSelf($request->user(), $page, $perPage);
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
        string $evaluation,
        GetDriverEvaluationDetailAction $action,
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->executeForSelf($request->user(), (int) $evaluation);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            (new AssignmentEvaluationDetailResource($result->data))->resolve(),
            $result->message
        );
    }
}
