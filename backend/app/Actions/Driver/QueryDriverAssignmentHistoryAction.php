<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Status;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;

/**
 * Paginated Driver-owned historical assignments (D11).
 * Terminal inactive Completed/Cancelled only. Identity from linked driver — never from query.
 */
class QueryDriverAssignmentHistoryAction
{
    public function execute(User $user, int $page = 1, int $perPage = 20, ?string $status = null): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));

        $statusIds = $this->terminalStatusIds($status);
        if ($statusIds === null) {
            return ApiResult::error('Invalid status filter.', 422, [
                'code' => 'INVALID_STATUS_FILTER',
            ]);
        }

        if ($statusIds === []) {
            return ApiResult::error('Assignment statuses are not configured.', 500);
        }

        $paginator = Assignment::query()
            ->forDriver($driver->id)
            ->where('is_active', false)
            ->whereIn('status_id', $statusIds)
            ->with(['status', 'shipment.status'])
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($paginator, 'Assignment history loaded');
    }

    /**
     * @return list<int>|null null = invalid filter
     */
    private function terminalStatusIds(?string $status): ?array
    {
        $wanted = [DriverAssignmentTransitionMap::COMPLETED, DriverAssignmentTransitionMap::CANCELLED];
        if ($status === 'completed') {
            $wanted = [DriverAssignmentTransitionMap::COMPLETED];
        } elseif ($status === 'cancelled') {
            $wanted = [DriverAssignmentTransitionMap::CANCELLED];
        } elseif ($status !== null && $status !== '') {
            return null;
        }

        return Status::query()
            ->where('type', 'assignment')
            ->whereIn('name', $wanted)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
