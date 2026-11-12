<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Vehicle;

class GetVehicleAction
{
    public function execute(string $vehicleId): ApiResult
    {
        try {
            $vehicle = Vehicle::with([
                'status',
                'vehicleType',
                'fuelType',
                'driver.status',
                'activeAssignment.driver',
                'activeAssignment.shipment',
                'activeAssignment.trailer',
                'trackingState',
            ])->find($vehicleId);

            if (!$vehicle) {
                throw new NotFoundException('Vehicle not found');
            }

            return ApiResult::success($vehicle, 'Vehicle retrieved successfully');
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve vehicle: ' . $e->getMessage(),
                500
            );
        }
    }
}
