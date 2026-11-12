<?php

namespace App\Http\Controllers;

use App\Actions\Driver\CreateDriverAction;
use App\Actions\Driver\DeleteDriverAction;
use App\Actions\Driver\GetDriverAction;
use App\Actions\Driver\GetDriverAvailabilityAction;
use App\Actions\Driver\GetDriverKPIsAction;
use App\Actions\Driver\QueryDriversAction;
use App\Actions\Driver\RestoreDriverAction;
use App\Actions\Driver\ToggleDriverStatusAction;
use App\Actions\Driver\UpdateDriverAction;
use App\Core\ApiResponse;
use App\Http\Requests\Driver\CreateDriverRequest;
use App\Http\Requests\Driver\QueryDriversRequest;
use App\Http\Requests\Driver\ToggleDriverStatusRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(QueryDriversRequest $request)
    {
        $this->authorize('view', Driver::class);

        $result = (new QueryDriversAction())->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\DriverCollection($result->data),
            $result->message
        );
    }

    private function findDriver(string $driverId): ?Driver
    {
        if (!is_numeric($driverId)) {
            return null;
        }

        return Driver::find($driverId);
    }

    public function show(string $driverId)
    {
        $driver = $this->findDriver($driverId);
        if (!$driver) {
            return ApiResponse::error('Driver not found.', 404);
        }

        $this->authorize('show', $driver);

        $result = (new GetDriverAction())->execute((int) $driverId);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\DriverResource($result->data),
            $result->message
        );
    }

    public function store(CreateDriverRequest $request)
    {
        $this->authorize('create', Driver::class);

        $result = (new CreateDriverAction())->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\DriverResource($result->data),
            $result->message,
            201
        );
    }

    public function update(UpdateDriverRequest $request, string $driverId)
    {
        $driver = $this->findDriver($driverId);
        if (!$driver) {
            return ApiResponse::error('Driver not found.', 404);
        }

        $this->authorize('update', $driver);

        $result = (new UpdateDriverAction())->execute($driver, $request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\DriverResource($result->data),
            $result->message
        );
    }

    public function toggleStatus(ToggleDriverStatusRequest $request, string $driverId)
    {
        $driver = $this->findDriver($driverId);
        if (!$driver) {
            return ApiResponse::error('Driver not found.', 404);
        }

        $this->authorize('toggleStatus', $driver);

        $result = (new ToggleDriverStatusAction())->execute($driver, $request->validated()['is_active']);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function destroy(string $driverId)
    {
        $driver = $this->findDriver($driverId);
        if (!$driver) {
            return ApiResponse::error('Driver not found.', 404);
        }

        $this->authorize('delete', $driver);

        $result = (new DeleteDriverAction())->execute($driver);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function restore(string $driverId)
    {
        $this->authorize('restore', Driver::class);

        $result = (new RestoreDriverAction())->execute($driverId);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function availability(GetDriverAvailabilityAction $action)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user || ! $user->isFleetXInternal()) {
            return ApiResponse::error('Forbidden', 403);
        }

        $this->authorize('view', Driver::class);

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function kpis(GetDriverKPIsAction $action)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (! $user || ! $user->isFleetXInternal()) {
            return ApiResponse::error('Forbidden', 403);
        }

        $this->authorize('view', Driver::class);

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
