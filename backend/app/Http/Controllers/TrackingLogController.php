<?php

namespace App\Http\Controllers;

use App\Actions\Tracking\CreateTrackingLogAction;
use App\Actions\Tracking\DeleteTrackingLogAction;
use App\Actions\Tracking\GetTrackingLogAction;
use App\Actions\Tracking\QueryTrackingLogsAction;
use App\Actions\Tracking\UpdateTrackingLogAction;
use App\Core\ApiResponse;
use App\Http\Requests\Tracking\CreateTrackingLogRequest;
use App\Http\Requests\Tracking\QueryTrackingLogsRequest;
use App\Http\Requests\Tracking\UpdateTrackingLogRequest;
use App\Http\Resources\Tracking\TrackingLogCollection;
use App\Http\Resources\Tracking\TrackingLogResource;
use App\Models\TrackingLog;
use Illuminate\Http\JsonResponse;

class TrackingLogController extends Controller
{
    public function index(QueryTrackingLogsRequest $request, QueryTrackingLogsAction $action): JsonResponse
    {
        $this->authorize('viewAny', TrackingLog::class);

        $result = $action->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new TrackingLogCollection($result->data),
            $result->message
        );
    }

    public function show(TrackingLog $trackingLog, GetTrackingLogAction $action): JsonResponse
    {
        $this->authorize('view', $trackingLog);

        $result = $action->execute($trackingLog->id);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new TrackingLogResource($result->data),
            $result->message
        );
    }

    public function store(CreateTrackingLogRequest $request, CreateTrackingLogAction $action): JsonResponse
    {
        $this->authorize('create', TrackingLog::class);

        $result = $action->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new TrackingLogResource($result->data),
            $result->message,
            201
        );
    }

    public function update(TrackingLog $trackingLog, UpdateTrackingLogRequest $request, UpdateTrackingLogAction $action): JsonResponse
    {
        $this->authorize('update', $trackingLog);

        $result = $action->execute($trackingLog, $request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new TrackingLogResource($result->data),
            $result->message
        );
    }

    public function destroy(TrackingLog $trackingLog, DeleteTrackingLogAction $action): JsonResponse
    {
        $this->authorize('delete', $trackingLog);

        $result = $action->execute($trackingLog);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
