<?php

namespace App\Http\Controllers;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Actions\Vehicle\DeleteVehicleAction;
use App\Actions\Vehicle\GetFleetKPIsAction;
use App\Actions\Vehicle\GetVehicleAction;
use App\Actions\Vehicle\GetVehicleAvailabilityAction;
use App\Actions\Vehicle\QueryVehiclesAction;
use App\Actions\Vehicle\RestoreVehicleAction;
use App\Actions\Vehicle\ToggleVehicleStatusAction;
use App\Actions\Vehicle\UpdateVehicleAction;
use App\Core\ApiResponse;
use App\Http\Requests\Vehicle\CreateVehicleRequest;
use App\Http\Requests\Vehicle\QueryVehiclesRequest;
use App\Http\Requests\Vehicle\ToggleVehicleStatusRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Resources\VehicleCollection;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    public function index(QueryVehiclesRequest $request, QueryVehiclesAction $action): JsonResponse
    {
        $this->authorize('view', Vehicle::class);

        $result = $action->execute($request->validated());

        if ($result->success) {
            return ApiResponse::success(
                new VehicleCollection($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function show(Vehicle $vehicle, GetVehicleAction $action): JsonResponse
    {
        $this->authorize('show', $vehicle);

        $result = $action->execute($vehicle->id);

        if ($result->success) {
            return ApiResponse::success(
                new VehicleResource($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function store(CreateVehicleRequest $request, CreateVehicleAction $action): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $result = $action->execute($request->validated());

        if ($result->success) {
            return ApiResponse::success(
                new VehicleResource($result->data),
                $result->message,
                201
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function update(Vehicle $vehicle, UpdateVehicleRequest $request, UpdateVehicleAction $action): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $result = $action->execute($vehicle, $request->validated());

        if ($result->success) {
            return ApiResponse::success(
                new VehicleResource($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function toggleStatus(Vehicle $vehicle, ToggleVehicleStatusRequest $request, ToggleVehicleStatusAction $action): JsonResponse
    {
        $this->authorize('toggleStatus', $vehicle);

        $result = $action->execute($vehicle, $request->validated()['is_active']);

        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function destroy(Vehicle $vehicle, DeleteVehicleAction $action): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $result = $action->execute($vehicle);

        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function restore(string $vehicle, RestoreVehicleAction $action): JsonResponse
    {
        $this->authorize('restore', Vehicle::class);

        $result = $action->execute($vehicle);

        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function availability(GetVehicleAvailabilityAction $action): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user || ! $user->isFleetXInternal()) {
            return ApiResponse::error('Forbidden', 403);
        }

        $this->authorize('view', Vehicle::class);

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function fleetKPIs(GetFleetKPIsAction $action): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user || ! $user->isFleetXInternal()) {
            return ApiResponse::error('Forbidden', 403);
        }

        $this->authorize('view', Vehicle::class);

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function pairDriver(
        \App\Http\Requests\Vehicle\PairDriverRequest $request,
        Vehicle $vehicle,
        \App\Actions\Vehicle\PairDriverToVehicleAction $action
    ): JsonResponse {
        $this->authorize('pairDriver', $vehicle);

        $result = $action->execute($vehicle, (int) $request->validated('driver_id'));

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new VehicleResource($result->data),
            $result->message
        );
    }

    public function unpairDriver(
        \Illuminate\Http\Request $request,
        Vehicle $vehicle,
        \App\Actions\Vehicle\UnpairDriverFromVehicleAction $action
    ): JsonResponse {
        $this->authorize('unpairDriver', $vehicle);

        $result = $action->execute($vehicle);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new VehicleResource($result->data),
            $result->message
        );
    }
}
