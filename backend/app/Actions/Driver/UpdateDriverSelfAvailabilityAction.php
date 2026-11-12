<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Status;
use App\Models\User;
use App\Support\DriverSelfServiceAvailability;
use Illuminate\Support\Facades\DB;

/**
 * Mutate the authenticated driver's Available ↔ Offline status (D13).
 *
 * Race-safe via lockForUpdate on the Driver row. Identity from $user->driver only.
 */
class UpdateDriverSelfAvailabilityAction
{
    public function execute(User $user, string $availability): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        if (! in_array($availability, [
            DriverSelfServiceAvailability::AVAILABLE,
            DriverSelfServiceAvailability::UNAVAILABLE,
        ], true)) {
            return ApiResult::error('Availability must be available or unavailable.', 422);
        }

        try {
            return DB::transaction(function () use ($driver, $availability) {
                /** @var Driver $locked */
                $locked = Driver::query()
                    ->whereKey($driver->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return ApiResult::error('Driver account is not linked.', 403);
                }

                $locked->load('status');

                $hasActiveAssignment = Assignment::query()
                    ->where('driver_id', $locked->id)
                    ->where('is_active', true)
                    ->exists();

                $statusName = $locked->status?->name;
                $isActive = (bool) $locked->is_active;
                $currentKey = DriverSelfServiceAvailability::keyFromStatusName($statusName, $isActive);
                $targetName = DriverSelfServiceAvailability::canonicalStatusName($availability);

                if (DriverSelfServiceAvailability::isOpsLocked($statusName, $isActive)) {
                    if ($availability === DriverSelfServiceAvailability::AVAILABLE) {
                        return ApiResult::error(
                            'Availability is managed by operations.',
                            409,
                            ['code' => DriverSelfServiceAvailability::LOCKED]
                        );
                    }

                    return ApiResult::success(
                        DriverSelfServiceAvailability::snapshot($statusName, $isActive, $hasActiveAssignment),
                        'Availability unchanged'
                    );
                }

                if ($hasActiveAssignment && $availability === DriverSelfServiceAvailability::UNAVAILABLE) {
                    if ($currentKey === DriverSelfServiceAvailability::UNAVAILABLE) {
                        return ApiResult::success(
                            DriverSelfServiceAvailability::snapshot($statusName, $isActive, $hasActiveAssignment),
                            'Availability unchanged'
                        );
                    }

                    return ApiResult::error(
                        "You can't change availability while you're on an active trip.",
                        409,
                        ['code' => DriverSelfServiceAvailability::BLOCKED_DURING_TRIP]
                    );
                }

                if ($statusName === $targetName) {
                    return ApiResult::success(
                        DriverSelfServiceAvailability::snapshot($statusName, $isActive, $hasActiveAssignment),
                        'Availability unchanged'
                    );
                }

                $statusId = Status::query()
                    ->where('name', $targetName)
                    ->where('type', 'driver')
                    ->value('id');

                if ($statusId === null) {
                    return ApiResult::error('Driver availability statuses are not configured.', 500);
                }

                $hasActiveAssignment = Assignment::query()
                    ->where('driver_id', $locked->id)
                    ->where('is_active', true)
                    ->exists();

                if ($hasActiveAssignment && $availability === DriverSelfServiceAvailability::UNAVAILABLE) {
                    return ApiResult::error(
                        "You can't change availability while you're on an active trip.",
                        409,
                        ['code' => DriverSelfServiceAvailability::BLOCKED_DURING_TRIP]
                    );
                }

                $locked->update(['status_id' => $statusId]);
                $locked->refresh()->load('status');

                $hasActiveAssignment = Assignment::query()
                    ->where('driver_id', $locked->id)
                    ->where('is_active', true)
                    ->exists();

                return ApiResult::success(
                    DriverSelfServiceAvailability::snapshot(
                        $locked->status?->name,
                        (bool) $locked->is_active,
                        $hasActiveAssignment
                    ),
                    $availability === DriverSelfServiceAvailability::AVAILABLE
                        ? 'You are now available'
                        : 'You are now unavailable'
                );
            });
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to update availability.', 500);
        }
    }
}
