<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DriverEvaluation\GetCurrentAssignmentEvaluationAction;
use App\Core\ApiResponse;
use App\Http\Resources\DriverEvaluation\CurrentAssignmentEvaluationResource;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ops read of current AssignmentEvaluation (DRIVER-EVAL-08A).
 * Auth: driverEvaluation.viewAny (Admin + Dispatcher) — not assignment CRUD.
 */
class AssignmentEvaluationCurrentController extends Controller
{
    public function show(
        Request $request,
        string $assignment,
        GetCurrentAssignmentEvaluationAction $action,
    ): JsonResponse {
        $model = Assignment::query()->findOrFail($assignment);

        $result = $action->executeForOps($request->user(), $model);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            (new CurrentAssignmentEvaluationResource($result->data))->resolve(),
            $result->message
        );
    }
}
