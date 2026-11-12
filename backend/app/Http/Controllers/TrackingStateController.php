<?php

namespace App\Http\Controllers;

use App\Actions\Tracking\QueryTrackingStatesAction;
use App\Core\ApiResponse;
use App\Http\Requests\Tracking\QueryTrackingStatesRequest;
use App\Http\Resources\Tracking\TrackingStateCollection;
use App\Http\Resources\Tracking\TrackingStateResource;
use App\Models\TrackingState;
use Illuminate\Http\JsonResponse;

class TrackingStateController extends Controller
{
    public function index(QueryTrackingStatesRequest $request, QueryTrackingStatesAction $action): JsonResponse
    {
        $this->authorize('viewAny', TrackingState::class);

        $result = $action->execute($request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new TrackingStateCollection($result->data),
            $result->message
        );
    }

    public function show(TrackingState $trackingState): JsonResponse
    {
        $this->authorize('view', $trackingState);

        $trackingState->load(['assignment', 'shipment', 'vehicle', 'driver', 'trailer']);

        return ApiResponse::success(
            new TrackingStateResource($trackingState),
            'Tracking state retrieved successfully'
        );
    }
}
