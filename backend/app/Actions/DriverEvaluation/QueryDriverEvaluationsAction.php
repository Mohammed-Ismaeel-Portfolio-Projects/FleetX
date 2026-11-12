<?php

declare(strict_types=1);

namespace App\Actions\DriverEvaluation;

use App\Core\ApiResult;
use App\Enums\AssignmentEvaluationStatus;
use App\Models\AssignmentEvaluation;
use App\Models\Driver;
use App\Models\User;
use App\Services\DriverEvaluation\Aggregation\EligibleEvaluationSelector;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Paginated authoritative evaluation history for a driver (DRIVER-EVAL-06).
 * Uses EVAL-05 selection semantics. Does not rescore.
 */
class QueryDriverEvaluationsAction
{
    public function __construct(
        private readonly EligibleEvaluationSelector $selector,
    ) {}

    public function executeForOps(User $user, Driver $driver, int $page = 1, int $perPage = 15): ApiResult
    {
        if (! $user->can('show', $driver)) {
            return ApiResult::error('Forbidden', 403);
        }

        if (! $user->can('viewAny', AssignmentEvaluation::class)) {
            return ApiResult::error('Forbidden', 403);
        }

        return ApiResult::success(
            $this->paginate((int) $driver->id, $page, $perPage),
            'Driver evaluations loaded'
        );
    }

    public function executeForSelf(User $user, int $page = 1, int $perPage = 15): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        return ApiResult::success(
            $this->paginate((int) $driver->id, $page, $perPage),
            'Driver evaluations loaded'
        );
    }

    private function paginate(int $driverId, int $page, int $perPage): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        $ids = $this->selector->latestFinalizedForDriver($driverId)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return AssignmentEvaluation::query()
            ->whereIn('id', $ids === [] ? [0] : $ids)
            ->whereIn('status', [
                AssignmentEvaluationStatus::COMPLETED->value,
                AssignmentEvaluationStatus::INCOMPLETE->value,
            ])
            ->with(['assignment:id,assignment_code', 'shipment:id,shipment_number'])
            ->orderByDesc('finalized_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
