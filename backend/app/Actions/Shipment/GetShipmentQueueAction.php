<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use Carbon\Carbon;

class GetShipmentQueueAction
{
    public function execute(): ApiResult
    {
        try {
            $today = Carbon::today();
            
            $shipments = Shipment::with(['customerCompany', 'status', 'activeAssignment.driver', 'activeAssignment.vehicle', 'route'])
                ->whereDate('planned_pickup_date', $today)
                ->orderBy('priority', 'desc')
                ->orderBy('planned_pickup_date', 'asc')
                ->get();

            return ApiResult::success($shipments, 'Shipment queue retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve shipment queue: ' . $e->getMessage(),
                500
            );
        }
    }
}
