<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class PairDriverToVehicleAction
{
    public function execute(Vehicle $vehicle, int $driverId): ApiResult
    {
        try {
            return DB::transaction(function () use ($vehicle, $driverId) {
                // Lock vehicle and driver for update
                $targetVehicle = Vehicle::whereKey($vehicle->id)->lockForUpdate()->first();
                if (!$targetVehicle) {
                    return ApiResult::notFound('Vehicle not found');
                }

                $driver = Driver::whereKey($driverId)->lockForUpdate()->first();
                if (!$driver) {
                    return ApiResult::notFound('Driver not found');
                }

                // Block pairing modification if vehicle or driver has an active assignment
                $vehicleHasActiveAssignment = \App\Models\Assignment::where('vehicle_id', $targetVehicle->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($vehicleHasActiveAssignment) {
                    return ApiResult::error('Cannot modify driver pairing while vehicle has an active assignment.', 422);
                }

                $driverHasActiveAssignment = \App\Models\Assignment::where('driver_id', $driverId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($driverHasActiveAssignment) {
                    return ApiResult::error('Cannot modify driver pairing while driver has an active assignment.', 422);
                }

                // If driver is currently paired with another vehicle, unpair it atomically
                $currentlyPairedVehicle = Vehicle::where('driver_id', $driverId)
                    ->where('id', '!=', $targetVehicle->id)
                    ->lockForUpdate()
                    ->first();

                if ($currentlyPairedVehicle) {
                    $currentlyPairedVehicle->update(['driver_id' => null]);
                }

                // Pair driver with target vehicle
                $targetVehicle->update(['driver_id' => $driverId]);

                return ApiResult::success(
                    $targetVehicle->fresh([
                        'status',
                        'vehicleType',
                        'fuelType',
                        'driver.status',
                        'activeAssignment.driver',
                        'trackingState'
                    ]),
                    'Driver paired with vehicle successfully'
                );
            });
        } catch (\Exception $e) {
            return ApiResult::error('Failed to pair driver: ' . $e->getMessage(), 500);
        }
    }
}
