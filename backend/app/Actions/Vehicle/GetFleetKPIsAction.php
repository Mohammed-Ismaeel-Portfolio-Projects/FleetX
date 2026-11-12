<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;

/**
 * Fleet KPI contract for the Fleet Management page.
 *
 * Single source of truth for availability buckets and utilization:
 * {@see GetVehicleAvailabilityAction}
 *
 * Mapping:
 * - assigned  → busy (vehicles with an active assignment, operationally committed)
 * - offline   → Out of Service + Inactive (active fleet)
 * - available → Available and not on an active assignment
 */
class GetFleetKPIsAction
{
    public function __construct(
        private readonly GetVehicleAvailabilityAction $availabilityAction
    ) {
    }

    public function execute(): ApiResult
    {
        try {
            $result = $this->availabilityAction->execute();

            if (!$result->success) {
                return $result;
            }

            /** @var array<string, float|int> $availability */
            $availability = $result->data;

            $kpiData = [
                'total_fleet' => $availability['total'],
                'available' => $availability['available'],
                'assigned' => $availability['busy'],
                'maintenance' => $availability['maintenance'],
                'offline' => $availability['offline'],
                'utilization' => (int) round((float) $availability['utilization_rate']),
                'available_percentage' => $availability['available_percentage'],
                'assigned_percentage' => $availability['busy_percentage'],
                'maintenance_percentage' => $availability['maintenance_percentage'],
                'offline_percentage' => $availability['offline_percentage'],
            ];

            return ApiResult::success($kpiData, 'Fleet KPIs retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve fleet KPIs: ' . $e->getMessage(),
                500
            );
        }
    }
}
