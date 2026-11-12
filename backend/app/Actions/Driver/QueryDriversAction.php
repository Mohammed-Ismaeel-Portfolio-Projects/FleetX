<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;

class QueryDriversAction
{
    public function execute(array $filters): ApiResult
    {
        try {
            $query = $this->buildQuery();

            $this->applyFilters($query, $filters);
            $this->applySearch($query, $filters);
            $this->applySorting($query, $filters);

            $perPage = $filters['per_page'] ?? 15;
            $page = $filters['page'] ?? 1;

            return ApiResult::success(
                $query->paginate($perPage, ['*'], 'page', $page),
                'Drivers retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve drivers: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function buildQuery(): Builder
    {
        return Driver::query()->with([
            'status',
            'activeAssignment.vehicle',
            'activeAssignment.shipment',
            'trackingState',
            'driverScore',
        ]);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach (['nationality', 'license_class', 'status_id', 'company_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Official status name filter (Available, Busy, Offline, On Leave, Suspended)
        if (!empty($filters['status'])) {
            $statusName = $filters['status'];
            $query->whereHas('status', function (Builder $q) use ($statusName) {
                $q->where('type', 'driver')->where('name', $statusName);
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['min_experience'])) {
            $query->where('years_of_experience', '>=', $filters['min_experience']);
        }

        if (isset($filters['max_experience'])) {
            $query->where('years_of_experience', '<=', $filters['max_experience']);
        }
    }

    protected function applySearch(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];

            $query->where(function ($q) use ($searchTerm) {
                $q->where('employee_number', 'ilike', "%{$searchTerm}%")
                    ->orWhere('first_name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('last_name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('email', 'ilike', "%{$searchTerm}%")
                    ->orWhere('phone', 'ilike', "%{$searchTerm}%")
                    ->orWhere('license_number', 'ilike', "%{$searchTerm}%");
            });
        }
    }

    protected function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? $filters['direction'] ?? 'desc';

        $allowedSortFields = [
            'employee_number',
            'first_name',
            'last_name',
            'email',
            'employment_date',
            'years_of_experience',
            'created_at',
            'updated_at',
        ];

        if (in_array($sortBy, $allowedSortFields, true)) {
            $query->orderBy($sortBy, $sortDirection);
            return;
        }

        $query->orderBy('created_at', 'desc');
    }
}
