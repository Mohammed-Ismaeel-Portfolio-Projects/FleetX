<?php

namespace App\Http\Controllers;

use App\Actions\Tracking\QueryLiveFleetSnapshotAction;
use App\Core\ApiResponse;
use App\Models\TrackingState;
use Illuminate\Http\JsonResponse;

/**
 * LiveFleetController
 *
 * Dispatcher live fleet snapshot — one row per active vehicle.
 * Does not replace TrackingStateController (admin/history).
 */
class LiveFleetController extends Controller
{
    public function index(QueryLiveFleetSnapshotAction $action): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user || ! $user->isFleetXInternal()) {
            return ApiResponse::error('Forbidden', 403);
        }

        $this->authorize('viewAny', TrackingState::class);

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
