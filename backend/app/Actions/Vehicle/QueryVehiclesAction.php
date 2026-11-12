<?php

namespace App\Actions\Vehicle;

use App\Core\ApiResult;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

class QueryVehiclesAction
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
                'Vehicles retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve vehicles: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function buildQuery(): Builder
    {
        return Vehicle::with([
            'status',
            'vehicleType',
            'fuelType',
            'driver.status',
            'activeAssignment.driver',
            'activeAssignment.shipment',
            'activeAssignment.trailer',
            'trackingState'
        ]);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach (['vehicle_type_id', 'company_id', 'fuel_type_id', 'status_id', 'manufacturer'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
    }

    protected function applySearch(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];

            $query->where(function ($q) use ($searchTerm) {
                $q->where('operational_code', 'ilike', "%{$searchTerm}%")
                    ->orWhere('fleet_number', 'ilike', "%{$searchTerm}%")
                    ->orWhere('plate_number', 'ilike', "%{$searchTerm}%")
                    ->orWhere('vin', 'ilike', "%{$searchTerm}%")
                    ->orWhere('manufacturer', 'ilike', "%{$searchTerm}%")
                    ->orWhere('model', 'ilike', "%{$searchTerm}%");
            });
        }
    }

    protected function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? $filters['direction'] ?? 'desc';

        $allowedSortFields = [
            'operational_code',
            'fleet_number',
            'plate_number',
            'manufacturer',
            'model',
            'production_year',
            'current_odometer',
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
