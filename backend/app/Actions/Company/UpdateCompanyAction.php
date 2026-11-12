<?php

namespace App\Actions\Company;

use App\Core\ApiResult;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Update Company Action
 * 
 * Contains ALL business logic for updating an existing company.
 * This action is responsible for:
 * - Validating business rules
 * - Updating only changed fields
 * - Saving changes
 * - Returning the updated company
 * 
 * This action does NOT handle API responses or JSON formatting.
 * It returns an ApiResult object for the controller to format.
 */
class UpdateCompanyAction
{
    /**
     * Execute the action.
     * 
     * @param Company $company The company to update
     * @param array $data Validated company data from UpdateCompanyRequest
     * @return ApiResult
     */
    public function execute(Company $company, array $data): ApiResult
    {
        try {
            // Use database transaction for data integrity
            return DB::transaction(function () use ($company, $data) {
                // Validate business rules
                $this->validateBusinessRules($company, $data);

                // Filter out unchanged fields
                $updateData = $this->filterChangedFields($company, $data);

                // If no changes, return company without updating timestamps
                if (empty($updateData)) {
                    return ApiResult::success($company, 'No changes detected');
                }

                // Prepare update data
                $updateData = $this->prepareUpdateData($updateData);

                // Update the company
                $company->update($updateData);

                // Refresh to get updated data
                $company->refresh();

                // Return success result
                return ApiResult::success($company, 'Company updated successfully');
            });
        } catch (\Exception $e) {
            // Return error result
            return ApiResult::error(
                'Failed to update company: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Validate business rules.
     * 
     * Business rules that cannot be expressed in form request validation.
     * 
     * @param Company $company
     * @param array $data
     * @throws \Exception
     */
    protected function validateBusinessRules(Company $company, array $data): void
    {
        // Check for duplicate legal name if being changed
        if (isset($data['legal_name']) && $data['legal_name'] !== $company->legal_name) {
            $existingLegalName = Company::withTrashed()
                ->where('legal_name', $data['legal_name'])
                ->where('id', '!=', $company->id)
                ->first();

            if ($existingLegalName) {
                throw new \Exception('A company with this legal name already exists.');
            }
        }

        // Additional business rules can be added here
        // - Status transition validation
        // - Industry restrictions
        // - Geographic restrictions
        // - etc.
    }

    /**
     * Filter out unchanged fields.
     * 
     * Only include fields that have actually changed.
     * 
     * @param Company $company
     * @param array $data
     * @return array
     */
    protected function filterChangedFields(Company $company, array $data): array
    {
        $changed = [];

        foreach ($data as $key => $value) {
            // Skip null values (optional fields not provided)
            if ($value === null) {
                continue;
            }

            // Compare with current value
            if ($company->$key != $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    /**
     * Prepare update data.
     * 
     * Removes protected fields and sets audit fields.
     * 
     * @param array $data
     * @return array
     */
    protected function prepareUpdateData(array $data): array
    {
        // Remove protected fields that should never be updated
        unset($data['id']);
        unset($data['created_by']);

        // Set audit field if authenticated user exists with valid UUID
        if (auth()->check()) {
            $userId = auth()->id();
            if (is_string($userId) && \Illuminate\Support\Str::isUuid($userId)) {
                $data['updated_by'] = $userId;
            }
        }

        return $data;
    }
}
