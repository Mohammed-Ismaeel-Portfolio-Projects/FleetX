<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class RestoreVehicleAction
{
    public function execute(string $vehicleId): ApiResult
    {
        try {
            return DB::transaction(function () use ($vehicleId) {
                $vehicle = Vehicle::withTrashed()->find($vehicleId);

                if (!$vehicle) {
                    throw new NotFoundException('Vehicle not found');
                }

                if (!$vehicle->trashed()) {
                    return ApiResult::success(null, 'Vehicle is already active');
                }

                if (auth()->check()) {
                    $vehicle->updated_by = auth()->id();
                    $vehicle->save();
                }

                $vehicle->restore();

                return ApiResult::success(null, 'Vehicle restored successfully');
            });
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to restore vehicle: ' . $e->getMessage(),
                500
            );
        }
    }
}
