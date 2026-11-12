<?php

namespace App\Http\Controllers\Driver;

use App\Actions\Driver\GetDriverSelfAvailabilityAction;
use App\Actions\Driver\UpdateDriverSelfAvailabilityAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\QueryDriverAvailabilityRequest;
use App\Http\Requests\Driver\UpdateDriverAvailabilityRequest;
use App\Http\Resources\Driver\DriverAvailabilityResource;
use Illuminate\Http\JsonResponse;

/**
 * Driver self-service availability (D13). Distinct from Ops GET /drivers/availability.
 */
class DriverAvailabilityController extends Controller
{
    public function show(
        QueryDriverAvailabilityRequest $request,
        GetDriverSelfAvailabilityAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            (new DriverAvailabilityResource($result->data))->resolve(),
            $result->message
        );
    }

    public function update(
        UpdateDriverAvailabilityRequest $request,
        UpdateDriverSelfAvailabilityAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute(
            $request->user(),
            (string) $request->validated('availability')
        );

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            (new DriverAvailabilityResource($result->data))->resolve(),
            $result->message
        );
    }
}
