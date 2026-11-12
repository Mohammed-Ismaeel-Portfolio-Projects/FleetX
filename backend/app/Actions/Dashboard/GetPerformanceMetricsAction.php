<?php

namespace App\Actions\Dashboard;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetPerformanceMetricsAction
{
    public function execute(): ApiResult
    {
        try {
            $today = Carbon::today();

            // Average dispatch time (time from assignment creation to status change to In Operation)
            $inOperationStatusId = DB::table('statuses')
                ->where('name', 'In Operation')
                ->where('type', 'assignment')
                ->value('id');

            $avgDispatchTime = Assignment::when($inOperationStatusId, function ($query) use ($inOperationStatusId) {
                return $query->where('status_id', $inOperationStatusId);
            })
            ->whereNotNull('assigned_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (assigned_at - created_at)) / 60) as avg_minutes')
            ->first()
            ->avg_minutes ?? 0;

            // ETA accuracy (percentage of shipments delivered within planned time)
            $completedStatusId = DB::table('statuses')
                ->where('name', 'Completed')
                ->where('type', 'shipment')
                ->value('id');

            $totalCompleted = Shipment::when($completedStatusId, function ($query) use ($completedStatusId) {
                return $query->where('status_id', $completedStatusId);
            })
            ->whereNotNull('actual_delivery_date')
            ->whereNotNull('planned_delivery_date')
            ->count();

            $onTimeDeliveries = Shipment::when($completedStatusId, function ($query) use ($completedStatusId) {
                return $query->where('status_id', $completedStatusId);
            })
            ->whereNotNull('actual_delivery_date')
            ->whereNotNull('planned_delivery_date')
            ->whereColumn('actual_delivery_date', '<=', 'planned_delivery_date')
            ->count();

            $etaAccuracy = $totalCompleted > 0 
                ? round(($onTimeDeliveries / $totalCompleted) * 100, 1) 
                : 0;

            // Completed today
            $completedToday = Shipment::when($completedStatusId, function ($query) use ($completedStatusId, $today) {
                return $query->where('status_id', $completedStatusId)->whereDate('actual_delivery_date', $today);
            }, function ($query) use ($today) {
                return $query->whereDate('actual_delivery_date', $today);
            })
            ->count();

            // On-time delivery percentage (same as ETA accuracy)
            $onTimePercentage = $etaAccuracy;

            // Overall efficiency (TODO: Cannot be calculated with current data - return null)
            $overallEfficiency = null;

            // Customer satisfaction (TODO: No review model exists - return null)
            $customerSatisfaction = null;

            // Cost savings (TODO: No cost savings tracking - return null)
            $costSavings = null;

            $performanceData = [
                'average_dispatch_time_minutes' => round($avgDispatchTime, 2),
                'eta_accuracy_percentage' => $etaAccuracy,
                'completed_today' => $completedToday,
                'on_time_delivery_percentage' => $onTimePercentage,
                'overall_efficiency' => $overallEfficiency,
                'customer_satisfaction' => $customerSatisfaction,
                'cost_savings' => $costSavings,
            ];

            return ApiResult::success($performanceData, 'Performance metrics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve performance metrics: ' . $e->getMessage(),
                500
            );
        }
    }
}
