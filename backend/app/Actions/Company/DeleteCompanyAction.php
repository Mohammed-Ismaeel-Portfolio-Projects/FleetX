<?php

namespace App\Actions\Company;

use App\Core\ApiResult;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Delete Company Action
 * 
 * Contains ALL logic for soft-deleting a company.
 * This action is responsible for:
 * - Checking if company is already deleted
 * - Performing soft delete
 * - Setting updated_by audit field
 * - Returning success result
 * 
 * This action does NOT handle API responses or JSON formatting.
 * It returns an ApiResult object for the controller to format.
 */
class DeleteCompanyAction
{
    /**
     * Execute the action.
     * 
     * @param Company $company The company to delete
     * @return ApiResult
     */
    public function execute(Company $company): ApiResult
    {
        try {
            // Use database transaction for data integrity
            return DB::transaction(function () use ($company) {
                // Check if already deleted
                if ($company->trashed()) {
                    return ApiResult::success(
                        null,
                        'Company already deleted'
                    );
                }

                // Set audit field if authenticated user exists with valid UUID
                if (auth()->check()) {
                    $userId = auth()->id();
                    if (is_string($userId) && \Illuminate\Support\Str::isUuid($userId)) {
                        $company->updated_by = $userId;
                        $company->save();
                    }
                }

                // Perform soft delete
                $company->delete();

                // Return success result
                return ApiResult::success(
                    null,
                    'Company deleted successfully'
                );
            });
        } catch (\Exception $e) {
            // Return error result
            return ApiResult::error(
                'Failed to delete company: ' . $e->getMessage(),
                500
            );
        }
    }
}
