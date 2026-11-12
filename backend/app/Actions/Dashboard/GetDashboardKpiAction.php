<?php

namespace App\Actions\Dashboard;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Issue;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class GetDashboardKpiAction
{
    /**
     * Operational KPI definitions (seeded status vocabularies only).
     *
     * - active_shipments: shipments not Completed / Cancelled
     * - pending_assignments: Draft + Assigned + Ready
     * - available_drivers: active drivers with no active assignment
     * - vehicles_in_transit: distinct vehicles on active assignments
     * - delayed_shipments: null (no reliable in-flight delay signal)
     * - critical_alerts: Issue rows typed critical / emergency
     */
    public function execute(): ApiResult
    {
        try {
            $terminalShipmentStatusIds = DB::table('statuses')
                ->where('type', 'shipment')
                ->whereIn('name', ['Completed', 'Cancelled'])
                ->pluck('id');

            $activeShipments = Shipment::query()
                ->when($terminalShipmentStatusIds->isNotEmpty(), function ($query) use ($terminalShipmentStatusIds) {
                    return $query->whereNotIn('status_id', $terminalShipmentStatusIds);
                })
                ->count();

            $pendingAssignmentStatusIds = DB::table('statuses')
                ->where('type', 'assignment')
                ->whereIn('name', ['Draft', 'Assigned', 'Ready'])
                ->pluck('id');

            $pendingAssignments = $pendingAssignmentStatusIds->isEmpty()
                ? 0
                : Assignment::query()
                    ->whereIn('status_id', $pendingAssignmentStatusIds)
                    ->count();

            $committedDriverIds = Assignment::query()
                ->active()
                ->whereNotNull('driver_id')
                ->distinct()
                ->pluck('driver_id');

            $lockedDriverStatusIds = DB::table('statuses')
                ->where('type', 'driver')
                ->whereIn('name', ['Offline', 'On Leave', 'Suspended'])
                ->pluck('id');

            $availableDrivers = Driver::query()
                ->where('is_active', true)
                ->when($lockedDriverStatusIds->isNotEmpty(), function ($query) use ($lockedDriverStatusIds) {
                    return $query->whereNotIn('status_id', $lockedDriverStatusIds);
                })
                ->when($committedDriverIds->isNotEmpty(), function ($query) use ($committedDriverIds) {
                    return $query->whereNotIn('id', $committedDriverIds);
                })
                ->count();

            $lockedVehicleStatusIds = DB::table('statuses')
                ->where('type', 'vehicle')
                ->whereIn('name', ['Maintenance', 'Out of Service', 'Inactive'])
                ->pluck('id');

            $committedVehicleIds = Assignment::query()
                ->active()
                ->whereNotNull('vehicle_id')
                ->distinct()
                ->pluck('vehicle_id');

            $vehiclesInTransit = $committedVehicleIds->isEmpty()
                ? 0
                : \App\Models\Vehicle::query()
                    ->where('is_active', true)
                    ->whereIn('id', $committedVehicleIds)
                    ->when($lockedVehicleStatusIds->isNotEmpty(), function ($query) use ($lockedVehicleStatusIds) {
                        return $query->whereNotIn('status_id', $lockedVehicleStatusIds);
                    })
                    ->count();

            // No reliable in-flight delay model exists.
            // Do not mislabel historically late completed deliveries as current delays.
            $delayedShipments = null;

            $resolvedIssueStatusId = DB::table('statuses')
                ->where('type', 'issue')
                ->where('name', 'Resolved')
                ->value('id');

            $criticalAlerts = Issue::query()
                ->where(function ($query) {
                    $query->where('type', 'critical')
                        ->orWhere('type', 'emergency');
                })
                ->when($resolvedIssueStatusId, function ($query) use ($resolvedIssueStatusId) {
                    return $query->where('status_id', '!=', $resolvedIssueStatusId);
                })
                ->count();

            $kpiData = [
                'active_shipments' => $activeShipments,
                'pending_assignments' => $pendingAssignments,
                'available_drivers' => $availableDrivers,
                'vehicles_in_transit' => $vehiclesInTransit,
                'delayed_shipments' => $delayedShipments,
                'critical_alerts' => $criticalAlerts,
            ];

            return ApiResult::success($kpiData, 'Dashboard KPIs retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve dashboard KPIs: ' . $e->getMessage(),
                500
            );
        }
    }
}
