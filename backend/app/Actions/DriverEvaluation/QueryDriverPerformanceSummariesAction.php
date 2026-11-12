<?php

declare(strict_types=1);

namespace App\Actions\DriverEvaluation;

use App\Core\ApiResult;
use App\Models\DriverScore;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Batch compact overall performance from driver_scores (DRIVER-EVAL-08A).
 * Single query — no scoring, aggregation, or telemetry.
 */
class QueryDriverPerformanceSummariesAction
{
    public const MAX_IDS = 100;

    /**
     * @param  list<int|string>  $driverIds
     */
    public function executeForOps(User $user, array $driverIds): ApiResult
    {
        if (! $user->can('driver.view')) {
            return ApiResult::error('Forbidden', 403);
        }

        $ids = collect($driverIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return ApiResult::error('At least one valid driver id is required.', 422, [
                'ids' => ['Provide a comma-separated list of driver ids.'],
            ]);
        }

        if ($ids->count() > self::MAX_IDS) {
            return ApiResult::error('Too many driver ids.', 422, [
                'ids' => ['Maximum '.self::MAX_IDS.' driver ids per request.'],
            ]);
        }

        /** @var Collection<int, DriverScore> $projections */
        $projections = DriverScore::query()
            ->whereIn('driver_id', $ids->all())
            ->get()
            ->keyBy(fn (DriverScore $row) => (int) $row->driver_id);

        $items = $ids->map(function (int $driverId) use ($projections) {
            return [
                'driver_id' => $driverId,
                'projection' => $projections->get($driverId),
            ];
        });

        return ApiResult::success($items, 'Driver performance summaries loaded');
    }
}
