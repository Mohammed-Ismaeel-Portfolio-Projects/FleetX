<?php

namespace App\Actions\Assignment;

use App\Core\ApiResult;
use App\Models\Assignment;
use Illuminate\Database\Eloquent\Builder;

class QueryAssignmentsAction
{
    public function execute(array $params): ApiResult
    {
        $query = Assignment::with(['shipment', 'driver', 'vehicle', 'trailer', 'status', 'assignedBy']);

        // Apply search filter
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('assignment_code', 'ilike', "%{$search}%")
                  ->orWhereHas('shipment', function (Builder $q) use ($search) {
                      $q->where('shipment_number', 'ilike', "%{$search}%");
                  })
                  ->orWhereHas('driver', function (Builder $q) use ($search) {
                      $q->where('employee_number', 'ilike', "%{$search}%")
                        ->orWhere('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%");
                  })
                  ->orWhereHas('vehicle', function (Builder $q) use ($search) {
                      $q->where('fleet_number', 'ilike', "%{$search}%")
                        ->orWhere('plate_number', 'ilike', "%{$search}%");
                  })
                  ->orWhereHas('trailer', function (Builder $q) use ($search) {
                      $q->where('trailer_code', 'ilike', "%{$search}%")
                        ->orWhere('fleet_number', 'ilike', "%{$search}%")
                        ->orWhere('plate_number', 'ilike', "%{$search}%");
                  });
            });
        }

        // Apply shipment filter
        if (!empty($params['shipment_id'])) {
            $query->where('shipment_id', $params['shipment_id']);
        }

        // Apply driver filter
        if (!empty($params['driver_id'])) {
            $query->where('driver_id', $params['driver_id']);
        }

        // Apply vehicle filter
        if (!empty($params['vehicle_id'])) {
            $query->where('vehicle_id', $params['vehicle_id']);
        }

        // Apply trailer filter
        if (!empty($params['trailer_id'])) {
            $query->where('trailer_id', $params['trailer_id']);
        }

        // Apply status filter
        if (!empty($params['status_id'])) {
            $query->where('status_id', $params['status_id']);
        }

        // Apply active filter
        if (isset($params['is_active'])) {
            $query->where('is_active', $params['is_active']);
        }

        // Apply assigned_by filter
        if (!empty($params['assigned_by'])) {
            $query->where('assigned_by', $params['assigned_by']);
        }

        // Apply date range filter
        if (!empty($params['assigned_from'])) {
            $query->where('assigned_at', '>=', $params['assigned_from']);
        }

        if (!empty($params['assigned_to'])) {
            $query->where('assigned_at', '<=', $params['assigned_to']);
        }

        // Apply sorting
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortDirection = $params['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        $assignments = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResult::success($assignments, 'Assignments retrieved successfully');
    }
}
