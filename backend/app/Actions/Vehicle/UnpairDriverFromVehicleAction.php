<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class UnpairDriverFromVehicleAction
{
    public function execute(Vehicle $vehicle): ApiResult
    {
        try {
            return DB::transaction(function () use ($vehicle) {
                $targetVehicle = Vehicle::whereKey($vehicle->id)->lockForUpdate()->first();
                if (!$targetVehicle) {
                    return ApiResult::notFound('Vehicle not found');
                }

                $vehicleHasActiveAssignment = \App\Models\Assignment::where('vehicle_id', $targetVehicle->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($vehicleHasActiveAssignment) {
                    return ApiResult::error('Cannot unpair driver while vehicle has an active assignment.', 422);
                }

                $targetVehicle->update(['driver_id' => null]);

                return ApiResult::success(
                    $targetVehicle->fresh([
                        'status',
                        'vehicleType',
                        'fuelType',
                        'driver.status',
                        'activeAssignment.driver',
                        'trackingState'
                    ]),
                    'Driver unpaired from vehicle successfully'
                );
            });
        } catch (\Exception $e) {
            return ApiResult::error('Failed to unpair driver: ' . $e->getMessage(), 500);
        }
    }
}
