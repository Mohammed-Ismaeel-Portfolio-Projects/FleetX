<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetActivityTimelineAction;
use App\Actions\Dashboard\GetDashboardKpiAction;
use App\Actions\Dashboard\GetPerformanceMetricsAction;
use App\Core\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function kpi(GetDashboardKpiAction $action): JsonResponse
    {
        $this->authorize('dashboard.view');

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function activity(GetActivityTimelineAction $action): JsonResponse
    {
        $this->authorize('dashboard.view');

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function performance(GetPerformanceMetricsAction $action): JsonResponse
    {
        $this->authorize('dashboard.view');

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }
}
