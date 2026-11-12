<?php

namespace App\Actions\Company;

use App\Core\ApiResult;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Toggle Company Status Action
 * 
 * Contains ALL logic for toggling company active status.
 * This action is responsible for:
 * - Checking if status change is needed
 * - Updating only is_active field
 * - Setting updated_by audit field
 * - Returning updated status
 * 
 * This action does NOT handle API responses or JSON formatting.
 * It returns an ApiResult object for the controller to format.
 */
class ToggleCompanyStatusAction
{
    /**
     * Execute the action.
     * 
     * @param Company $company The company to update
     * @param bool $isActive The new active status
     * @return ApiResult
     */
    public function execute(Company $company, bool $isActive): ApiResult
    {
        try {
            // Use database transaction for data integrity
            return DB::transaction(function () use ($company, $isActive) {
                // Check if already in requested state
                if ($company->is_active === $isActive) {
                    // Return success without update
                    return ApiResult::success(
                        [
                            'id' => $company->id,
                            'is_active' => $company->is_active,
                        ],
                        'Company already has the requested status'
                    );
                }

                // Prepare update data - only is_active and updated_by
                $updateData = [
                    'is_active' => $isActive,
                ];

                // Set audit field if authenticated user exists with valid UUID
                if (auth()->check()) {
                    $userId = auth()->id();
                    if (is_string($userId) && \Illuminate\Support\Str::isUuid($userId)) {
                        $updateData['updated_by'] = $userId;
                    }
                }

                // Update the company
                $company->update($updateData);

                // Refresh to get updated data
                $company->refresh();

                // Return success result with status only
                return ApiResult::success(
                    [
                        'id' => $company->id,
                        'is_active' => $company->is_active,
                    ],
                    $isActive ? 'Company activated successfully' : 'Company deactivated successfully'
                );
            });
        } catch (\Exception $e) {
            // Return error result
            return ApiResult::error(
                'Failed to update company status: ' . $e->getMessage(),
                500
            );
        }
    }
}
