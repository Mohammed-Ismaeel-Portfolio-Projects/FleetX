<?php

namespace App\Actions\Issue;

use App\Core\ApiResult;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Builder;

class QueryIssuesAction
{
    public function execute(array $params): ApiResult
    {
        try {
            $query = Issue::with(['shipment', 'driver', 'status']);

            // Apply type filter
            if (!empty($params['type'])) {
                $query->where('type', $params['type']);
            }

            // Apply status filter
            if (!empty($params['status_id'])) {
                $query->where('status_id', $params['status_id']);
            }

            // Apply shipment filter
            if (!empty($params['shipment_id'])) {
                $query->where('shipment_id', $params['shipment_id']);
            }

            // Apply driver filter
            if (!empty($params['driver_id'])) {
                $query->where('driver_id', $params['driver_id']);
            }

            // Apply sorting
            $sortBy = $params['sort_by'] ?? 'created_at';
            $sortDirection = $params['sort_direction'] ?? 'desc';
            $query->orderBy($sortBy, $sortDirection);

            // Apply pagination
            $perPage = $params['per_page'] ?? 15;
            $page = $params['page'] ?? 1;

            $issues = $query->paginate($perPage, ['*'], 'page', $page);

            return ApiResult::success($issues, 'Issues retrieved successfully');
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve issues: ' . $e->getMessage(),
                500
            );
        }
    }
}
