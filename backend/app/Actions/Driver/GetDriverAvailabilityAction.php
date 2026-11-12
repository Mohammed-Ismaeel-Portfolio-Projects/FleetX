<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Driver;
use App\Support\DriverOperationalAvailability;
use Illuminate\Support\Facades\DB;

class GetDriverAvailabilityAction
{
    /**
     * Official driver status vocabulary (DriverSeeder):
     * Available, Busy, Offline, On Leave, Suspended.
     *
     * Aggregate buckets follow DriverOperationalAvailability rules:
     * - Locked (Offline / On Leave / Suspended) counted by official status
     * - Busy = active assignment and not locked
     * - Available = residual free capacity (not locked, no active assignment)
     *
     * Legacy availability-contract aliases:
     * - driving → busy
     * - break   → on_leave
     */
    public function execute(): ApiResult
    {
        try {
            $officialNames = array_values(array_unique(array_merge(
                ['Available', 'Busy'],
                DriverOperationalAvailability::LOCKED
            )));

            $statusIds = DB::table('statuses')
                ->where('type', 'driver')
                ->whereIn('name', $officialNames)
                ->pluck('id', 'name');

            $offlineStatusId = $statusIds['Offline'] ?? null;
            $onLeaveStatusId = $statusIds['On Leave'] ?? null;
            $suspendedStatusId = $statusIds['Suspended'] ?? null;

            $lockedStatusIds = [];
            foreach (DriverOperationalAvailability::LOCKED as $lockedName) {
                if (isset($statusIds[$lockedName])) {
                    $lockedStatusIds[] = $statusIds[$lockedName];
                }
            }

            $committedDriverIds = Assignment::query()
                ->active()
                ->whereNotNull('driver_id')
                ->distinct()
                ->pluck('driver_id');

            $totalCount = Driver::where('is_active', true)->count();

            $offlineCount = $offlineStatusId
                ? Driver::where('is_active', true)
                    ->where('status_id', $offlineStatusId)
                    ->count()
                : 0;

            $onLeaveCount = $onLeaveStatusId
                ? Driver::where('is_active', true)
                    ->where('status_id', $onLeaveStatusId)
                    ->count()
                : 0;

            $suspendedCount = $suspendedStatusId
                ? Driver::where('is_active', true)
                    ->where('status_id', $suspendedStatusId)
                    ->count()
                : 0;

            // Busy = operationally committed drivers only (active assignment, not locked).
            $busyCount = $committedDriverIds->isEmpty()
                ? 0
                : Driver::where('is_active', true)
                    ->whereIn('id', $committedDriverIds)
                    ->when(!empty($lockedStatusIds), function ($query) use ($lockedStatusIds) {
                        return $query->whereNotIn('status_id', $lockedStatusIds);
                    })
                    ->count();

            // Available = residual free active drivers (not busy, not locked).
            $availableQuery = Driver::where('is_active', true)
                ->when(!empty($lockedStatusIds), function ($query) use ($lockedStatusIds) {
                    return $query->whereNotIn('status_id', $lockedStatusIds);
                })
                ->when($committedDriverIds->isNotEmpty(), function ($query) use ($committedDriverIds) {
                    return $query->whereNotIn('id', $committedDriverIds);
                });

            $availableCount = (clone $availableQuery)->count();

            $topDrivers = (clone $availableQuery)
                ->with(['trackingState', 'status'])
                ->limit(4)
                ->get()
                ->map(function ($driver) {
                    return [
                        'id' => $driver->id,
                        'employee_number' => $driver->employee_number,
                        'full_name' => $driver->full_name,
                        'phone' => $driver->phone,
                        'status' => $driver->status ? $driver->status->name : null,
                        'dispatch_availability' => DriverOperationalAvailability::fromStatus(
                            $driver->status,
                            false
                        ),
                        'location' => $driver->trackingState ? [
                            'latitude' => $driver->trackingState->latitude,
                            'longitude' => $driver->trackingState->longitude,
                            'last_update' => $driver->trackingState->last_update_at?->toIso8601String(),
                        ] : null,
                    ];
                });

            $pct = static fn (int $count) => $totalCount > 0
                ? round(($count / $totalCount) * 100, 1)
                : 0;

            $availabilityData = [
                'total' => $totalCount,
                'available' => $availableCount,
                'available_percentage' => $pct($availableCount),
                'busy' => $busyCount,
                'busy_percentage' => $pct($busyCount),
                // Legacy aliases for Dispatcher Dashboard contract only
                'driving' => $busyCount,
                'driving_percentage' => $pct($busyCount),
                'break' => $onLeaveCount,
                'break_percentage' => $pct($onLeaveCount),
                'offline' => $offlineCount,
                'offline_percentage' => $pct($offlineCount),
                'on_leave' => $onLeaveCount,
                'on_leave_percentage' => $pct($onLeaveCount),
                'suspended' => $suspendedCount,
                'suspended_percentage' => $pct($suspendedCount),
                'top_drivers' => $topDrivers,
            ];

            return ApiResult::success($availabilityData, 'Driver availability retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve driver availability: ' . $e->getMessage(),
                500
            );
        }
    }
}
