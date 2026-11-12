<?php

namespace App\Actions\Trailer;

use App\Core\ApiResult;
use App\Models\Trailer;
use Illuminate\Database\Eloquent\Builder;

class QueryTrailersAction
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
                'Trailers retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResult::error(
                'Failed to retrieve trailers: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function buildQuery(): Builder
    {
        return Trailer::query();
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach (['trailer_type', 'manufacturer', 'status_id', 'company_id'] as $field) {
            if (isset($filters[$field]) && !empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['available_only'])) {
            $query->where('is_active', true)
                ->whereDoesntHave('assignments', function ($q) {
                    $q->where('is_active', true)->whereNull('deleted_at');
                });
        }
    }

    protected function applySearch(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];

            $query->where(function ($q) use ($searchTerm) {
                $q->where('trailer_code', 'ilike', "%{$searchTerm}%")
                    ->orWhere('fleet_number', 'ilike', "%{$searchTerm}%")
                    ->orWhere('plate_number', 'ilike', "%{$searchTerm}%")
                    ->orWhere('trailer_type', 'ilike', "%{$searchTerm}%")
                    ->orWhere('manufacturer', 'ilike', "%{$searchTerm}%")
                    ->orWhere('model', 'ilike', "%{$searchTerm}%");
            });
        }
    }

    protected function applySorting(Builder $query, array $filters): void
    {
        if (!empty($filters['requested_trailer_type'])) {
            $reqType = addslashes(strtolower((string) $filters['requested_trailer_type']));
            $query->orderByRaw("CASE WHEN LOWER(trailer_type) = '{$reqType}' THEN 0 ELSE 1 END");
        }

        $sortBy = $filters['sort_by'] ?? $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? $filters['direction'] ?? 'desc';

        $allowedSortFields = [
            'trailer_code',
            'fleet_number',
            'plate_number',
            'trailer_type',
            'manufacturer',
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
