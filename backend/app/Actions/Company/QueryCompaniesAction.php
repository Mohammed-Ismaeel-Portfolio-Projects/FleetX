<?php

namespace App\Actions\Company;

use App\Core\ApiResult;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query Companies Action
 * 
 * Contains ALL query logic for listing companies.
 * This action is responsible for:
 * - Building Eloquent query
 * - Applying filters
 * - Applying search
 * - Applying sorting
 * - Returning paginated results
 * 
 * This action does NOT handle API responses or JSON formatting.
 * It returns an ApiResult object for the controller to format.
 */
class QueryCompaniesAction
{
    /**
     * Execute the action.
     * 
     * @param array $filters Validated filter data from ListCompaniesRequest
     * @return ApiResult
     */
    public function execute(array $filters): ApiResult
    {
        try {
            // Build base query
            $query = $this->buildQuery($filters);

            // Apply filters
            $this->applyFilters($query, $filters);

            // Apply search
            $this->applySearch($query, $filters);

            // Apply sorting
            $this->applySorting($query, $filters);

            // Get pagination parameters
            $perPage = $filters['per_page'] ?? 15;
            $page = $filters['page'] ?? 1;

            // Execute paginated query
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            // Return success result
            return ApiResult::success($paginator, 'Companies retrieved successfully');
        } catch (\Exception $e) {
            // Return error result
            return ApiResult::error(
                'Failed to retrieve companies: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Build base query.
     * 
     * @param array $filters
     * @return Builder
     */
    protected function buildQuery(array $filters): Builder
    {
        return Company::query();
    }

    /**
     * Apply filters to query.
     * 
     * Only applies filters when they are provided.
     * 
     * @param Builder $query
     * @param array $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Filter by status
        if (isset($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        // Filter by country
        if (isset($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        // Filter by industry
        if (isset($filters['industry_id'])) {
            $query->where('industry_id', $filters['industry_id']);
        }

        // Filter by company size
        if (isset($filters['company_size_id'])) {
            $query->where('company_size_id', $filters['company_size_id']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by company type
        if (!empty($filters['company_type'])) {
            $type = $filters['company_type'];
            if ($type === 'partner_carrier') {
                $query->whereIn('company_type', [\App\Enums\CompanyType::PARTNER_CARRIER, \App\Enums\CompanyType::HYBRID]);
            } elseif ($type === 'client') {
                $query->whereIn('company_type', [\App\Enums\CompanyType::CLIENT, \App\Enums\CompanyType::HYBRID]);
            } else {
                $query->where('company_type', $type);
            }
        }
    }

    /**
     * Apply search to query.
     * 
     * Searches across company_name, legal_name, company_code, and primary_email.
     * 
     * @param Builder $query
     * @param array $filters
     */
    protected function applySearch(Builder $query, array $filters): void
    {
        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = $filters['search'];

            $query->where(function ($q) use ($searchTerm) {
                $q->where('company_name', 'ilike', "%{$searchTerm}%")
                  ->orWhere('legal_name', 'ilike', "%{$searchTerm}%")
                  ->orWhere('company_code', 'ilike', "%{$searchTerm}%")
                  ->orWhere('primary_email', 'ilike', "%{$searchTerm}%");
            });
        }
    }

    /**
     * Apply sorting to query.
     * 
     * @param Builder $query
     * @param array $filters
     */
    protected function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        // Validate sort field to prevent SQL injection
        $allowedSortFields = [
            'company_name',
            'legal_name',
            'company_code',
            'country',
            'city',
            'created_at',
            'updated_at',
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            // Default sorting
            $query->orderBy('created_at', 'desc');
        }
    }
}
