<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class GetVehicleAvailabilityAction
{
    /**
     * Official vehicle status vocabulary (VehicleStatusSeeder).
     *
     * Widget buckets (endpoint contract unchanged):
     * - available   → Available and not on an active assignment
     * - busy        → distinct vehicles with an active assignment (operationally committed)
     * - maintenance → Maintenance
     * - offline     → Out of Service + Inactive (within the active fleet / total)
     */
    public function execute(): ApiResult
    {
        try {
            $statusIds = DB::table('statuses')
                ->where('type', 'vehicle')
                ->whereIn('name', [
                    'Available',
                    'Maintenance',
                    'Out of Service',
                    'Inactive',
                ])
                ->pluck('id', 'name');

            $availableStatusId = $statusIds['Available'] ?? null;
            $maintenanceStatusId = $statusIds['Maintenance'] ?? null;
            $outOfServiceStatusId = $statusIds['Out of Service'] ?? null;
            $inactiveStatusId = $statusIds['Inactive'] ?? null;

            $lockedStatusIds = array_values(array_filter([
                $maintenanceStatusId,
                $outOfServiceStatusId,
                $inactiveStatusId,
            ]));

            // Busy = operationally committed vehicles only:
            // distinct vehicle_id on active assignments, excluding locked statuses.
            $committedVehicleIds = Assignment::query()
                ->active()
                ->whereNotNull('vehicle_id')
                ->distinct()
                ->pluck('vehicle_id');

            $totalCount = Vehicle::where('is_active', true)->count();

            $maintenanceCount = $maintenanceStatusId
                ? Vehicle::where('is_active', true)
                    ->where('status_id', $maintenanceStatusId)
                    ->count()
                : 0;

            $offlineStatusIds = array_values(array_filter([
                $outOfServiceStatusId,
                $inactiveStatusId,
            ]));

            $offlineCount = !empty($offlineStatusIds)
                ? Vehicle::where('is_active', true)
                    ->whereIn('status_id', $offlineStatusIds)
                    ->count()
                : 0;

            $busyCount = $committedVehicleIds->isEmpty()
                ? 0
                : Vehicle::where('is_active', true)
                    ->whereIn('id', $committedVehicleIds)
                    ->when(!empty($lockedStatusIds), function ($query) use ($lockedStatusIds) {
                        return $query->whereNotIn('status_id', $lockedStatusIds);
                    })
                    ->count();

            $availableCount = $availableStatusId
                ? Vehicle::where('is_active', true)
                    ->where('status_id', $availableStatusId)
                    ->when($committedVehicleIds->isNotEmpty(), function ($query) use ($committedVehicleIds) {
                        return $query->whereNotIn('id', $committedVehicleIds);
                    })
                    ->count()
                : 0;

            $utilizationRate = $totalCount > 0
                ? round(($busyCount / $totalCount) * 100, 1)
                : 0;

            $availabilityData = [
                'total' => $totalCount,
                'available' => $availableCount,
                'available_percentage' => $totalCount > 0 ? round(($availableCount / $totalCount) * 100, 1) : 0,
                'busy' => $busyCount,
                'busy_percentage' => $totalCount > 0 ? round(($busyCount / $totalCount) * 100, 1) : 0,
                'maintenance' => $maintenanceCount,
                'maintenance_percentage' => $totalCount > 0 ? round(($maintenanceCount / $totalCount) * 100, 1) : 0,
                'offline' => $offlineCount,
                'offline_percentage' => $totalCount > 0 ? round(($offlineCount / $totalCount) * 100, 1) : 0,
                'utilization_rate' => $utilizationRate,
            ];

            return ApiResult::success($availabilityData, 'Vehicle availability retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve vehicle availability: ' . $e->getMessage(),
                500
            );
        }
    }
}
