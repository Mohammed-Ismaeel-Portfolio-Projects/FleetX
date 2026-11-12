<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

/**
 * Canonical shipment KPI buckets (ShipmentSeeder vocabulary).
 *
 * pending     → Draft, Pending Review, Approved, Waiting Assignment
 * assigned    → Assigned, Ready For Pickup
 * in_transit  → Picked Up, In Transit, At Customs, Out For Delivery
 * delivered   → Delivered, Completed
 * delayed     → not in official vocabulary → always 0 (no invented Delayed status)
 * urgent      → priority = urgent (replaces invalid Critical literal)
 */
class GetShipmentKPIsAction
{
    public function execute(): ApiResult
    {
        try {
            $statusIds = DB::table('statuses')
                ->where('type', 'shipment')
                ->whereIn('name', [
                    'Draft',
                    'Pending Review',
                    'Approved',
                    'Waiting Assignment',
                    'Assigned',
                    'Ready For Pickup',
                    'Picked Up',
                    'In Transit',
                    'At Customs',
                    'Out For Delivery',
                    'Delivered',
                    'Completed',
                    'Cancelled',
                ])
                ->pluck('id', 'name');

            $pendingIds = array_values(array_filter([
                $statusIds['Draft'] ?? null,
                $statusIds['Pending Review'] ?? null,
                $statusIds['Approved'] ?? null,
                $statusIds['Waiting Assignment'] ?? null,
            ]));

            $assignedIds = array_values(array_filter([
                $statusIds['Assigned'] ?? null,
                $statusIds['Ready For Pickup'] ?? null,
            ]));

            $inTransitIds = array_values(array_filter([
                $statusIds['Picked Up'] ?? null,
                $statusIds['In Transit'] ?? null,
                $statusIds['At Customs'] ?? null,
                $statusIds['Out For Delivery'] ?? null,
            ]));

            $deliveredIds = array_values(array_filter([
                $statusIds['Delivered'] ?? null,
                $statusIds['Completed'] ?? null,
            ]));

            $totalCount = Shipment::count();

            $pendingCount = empty($pendingIds)
                ? 0
                : Shipment::whereIn('status_id', $pendingIds)->count();

            $assignedCount = empty($assignedIds)
                ? 0
                : Shipment::whereIn('status_id', $assignedIds)->count();

            $inTransitCount = empty($inTransitIds)
                ? 0
                : Shipment::whereIn('status_id', $inTransitIds)->count();

            $deliveredCount = empty($deliveredIds)
                ? 0
                : Shipment::whereIn('status_id', $deliveredIds)->count();

            // No official Delayed shipment status — do not invent counts.
            $delayedCount = 0;

            $urgentCount = Shipment::where('priority', 'urgent')->count();

            $kpiData = [
                'total_fleet' => $totalCount,
                'pending' => $pendingCount,
                'assigned' => $assignedCount,
                'in_transit' => $inTransitCount,
                'delivered' => $deliveredCount,
                'delayed' => $delayedCount,
                'urgent' => $urgentCount,
                // Kept for older clients; same as urgent under official priority enum.
                'critical' => $urgentCount,
                'pending_percentage' => $totalCount > 0 ? round(($pendingCount / $totalCount) * 100, 1) : 0,
                'assigned_percentage' => $totalCount > 0 ? round(($assignedCount / $totalCount) * 100, 1) : 0,
                'in_transit_percentage' => $totalCount > 0 ? round(($inTransitCount / $totalCount) * 100, 1) : 0,
                'delivered_percentage' => $totalCount > 0 ? round(($deliveredCount / $totalCount) * 100, 1) : 0,
                'delayed_percentage' => 0,
            ];

            return ApiResult::success($kpiData, 'Shipment KPIs retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve shipment KPIs: ' . $e->getMessage(),
                500
            );
        }
    }
}
