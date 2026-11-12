<?php

namespace App\Actions\Dashboard;

use App\Core\ApiResult;
use App\Models\AuditLog;
use App\Models\TrackingLog;
use App\Models\Issue;
use Carbon\Carbon;

class GetActivityTimelineAction
{
    public function execute(): ApiResult
    {
        try {
            $activities = collect();

            // Get recent audit logs
            $auditLogs = AuditLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'type' => 'audit',
                        'message' => $log->action,
                        'description' => $log->user ? "User {$log->user->name} performed {$log->action}" : "Performed {$log->action}",
                        'timestamp' => $log->created_at->toIso8601String(),
                        'icon' => 'info',
                    ];
                });

            // Get recent tracking logs
            $trackingLogs = TrackingLog::with('shipment')
                ->orderBy('recorded_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'type' => 'tracking',
                        'message' => 'Location Update',
                        'description' => $log->shipment ? "Shipment {$log->shipment->shipment_number} location updated" : 'Location updated',
                        'timestamp' => $log->recorded_at->toIso8601String(),
                        'icon' => 'success',
                    ];
                });

            // Get recent issues
            $issues = Issue::with(['shipment', 'driver', 'status'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($issue) {
                    return [
                        'id' => $issue->id,
                        'type' => 'issue',
                        'message' => $issue->type,
                        'description' => $issue->description,
                        'timestamp' => $issue->created_at->toIso8601String(),
                        'icon' => 'warning',
                    ];
                });

            // Merge and sort by timestamp
            $activities = $auditLogs
                ->concat($trackingLogs)
                ->concat($issues)
                ->sortByDesc('timestamp')
                ->values()
                ->take(20);

            return ApiResult::success($activities, 'Activity timeline retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve activity timeline: ' . $e->getMessage(),
                500
            );
        }
    }
}
