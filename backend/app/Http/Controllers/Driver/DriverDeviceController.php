<?php

namespace App\Http\Controllers\Driver;

use App\Actions\Driver\QueryDriverDevicesAction;
use App\Actions\Driver\RegisterDriverDeviceAction;
use App\Actions\Driver\UnregisterDriverDeviceAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\QueryDriverDevicesRequest;
use App\Http\Requests\Driver\RegisterDriverDeviceRequest;
use App\Http\Requests\Driver\UnregisterDriverDeviceRequest;
use App\Http\Resources\Driver\DriverDeviceResource;
use Illuminate\Http\JsonResponse;

/**
 * Driver device registration (D12). Distinct from Ops / Client APIs.
 */
class DriverDeviceController extends Controller
{
    public function register(
        RegisterDriverDeviceRequest $request,
        RegisterDriverDeviceAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user(), $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(new DriverDeviceResource($result->data), $result->message);
    }

    public function unregister(
        UnregisterDriverDeviceRequest $request,
        UnregisterDriverDeviceAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user(), $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        $payload = is_object($result->data) && isset($result->data->id)
            ? new DriverDeviceResource($result->data)
            : $result->data;

        return ApiResponse::success($payload, $result->message);
    }

    public function index(
        QueryDriverDevicesRequest $request,
        QueryDriverDevicesAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        $result = $action->execute($request->user());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            DriverDeviceResource::collection($result->data),
            $result->message
        );
    }
}
