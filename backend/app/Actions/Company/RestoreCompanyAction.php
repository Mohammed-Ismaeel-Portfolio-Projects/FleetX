<?php

namespace App\Actions\Company;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Restore Company Action
 * 
 * Contains ALL logic for restoring a soft-deleted company.
 * This action is responsible for:
 * - Finding company including soft-deleted records
 * - Checking if company exists
 * - Checking if company is already active (not deleted)
 * - Restoring soft-deleted company
 * - Setting updated_by audit field
 * - Returning success result
 * 
 * This action does NOT handle API responses or JSON formatting.
 * It returns an ApiResult object for the controller to format.
 */
class RestoreCompanyAction
{
    /**
     * Execute the action.
     * 
     * @param string $companyId UUID of the company to restore
     * @return ApiResult
     */
    public function execute(string $companyId): ApiResult
    {
        try {
            // Use database transaction for data integrity
            return DB::transaction(function () use ($companyId) {
                // Find company including soft-deleted records
                $company = Company::withTrashed()->find($companyId);

                // Check if company exists
                if (!$company) {
                    throw new NotFoundException('Company not found');
                }

                // Check if company is already active (not deleted)
                if (!$company->trashed()) {
                    return ApiResult::success(
                        null,
                        'Company is already active'
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

                // Restore the soft-deleted company
                $company->restore();

                // Return success result
                return ApiResult::success(
                    null,
                    'Company restored successfully'
                );
            });
        } catch (NotFoundException $e) {
            // Re-throw NotFoundException for proper handling
            throw $e;
        } catch (\Exception $e) {
            // Return error result for other exceptions
            return ApiResult::error(
                'Failed to restore company: ' . $e->getMessage(),
                500
            );
        }
    }
}
