<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Driver;

class GetDriverAction
{
    public function execute(string $driverId): ApiResult
    {
        try {
            $driver = Driver::query()
                ->with([
                    'status',
                    'activeAssignment.vehicle',
                    'activeAssignment.shipment',
                    'trackingState',
                    'driverScore',
                ])
                ->withCount('assignments')
                ->find($driverId);

            if (!$driver) {
                throw new NotFoundException('Driver not found');
            }

            return ApiResult::success($driver, 'Driver retrieved successfully');
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve driver: ' . $e->getMessage(),
                500
            );
        }
    }
}
