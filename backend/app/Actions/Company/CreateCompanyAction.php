<?php

namespace App\Actions\Company;

use App\Core\ApiResult;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Create Company Action
 * 
 * Contains ALL business logic for creating a new company.
 * This action is responsible for:
 * - Validating business rules
 * - Normalizing input
 * - Storing the company
 * - Returning the created company
 * 
 * This action does NOT handle API responses or JSON formatting.
 * It returns an ApiResult object for the controller to format.
 */
class CreateCompanyAction
{
    /**
     * Execute the action.
     * 
     * @param array $data Validated company data from CreateCompanyRequest
     * @return ApiResult
     */
    public function execute(array $data): ApiResult
    {
        try {
            // Use database transaction for data integrity
            return DB::transaction(function () use ($data) {
                // Validate business rules
                $this->validateBusinessRules($data);

                // Prepare company data
                $companyData = $this->prepareCompanyData($data);

                // Create the company
                $company = Company::create($companyData);

                // Return success result
                return ApiResult::success($company, 'Company created successfully');
            });
        } catch (\Exception $e) {
            // Return error result
            return ApiResult::error(
                'Failed to create company: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Validate business rules.
     * 
     * Business rules that cannot be expressed in form request validation.
     * 
     * @param array $data
     * @throws \Exception
     */
    protected function validateBusinessRules(array $data): void
    {
        // Check for duplicate legal name if business requires uniqueness
        // This is a business rule that may vary by requirements
        if (isset($data['legal_name'])) {
            $existingLegalName = Company::withTrashed()
                ->where('legal_name', $data['legal_name'])
                ->first();

            if ($existingLegalName) {
                throw new \Exception('A company with this legal name already exists.');
            }
        }

        // Additional business rules can be added here
        // - Credit limit checks
        // - Industry restrictions
        // - Geographic restrictions
        // - etc.
    }

    /**
     * Prepare company data for storage.
     * 
     * Sets default values and ensures data integrity.
     * 
     * @param array $data
     * @return array
     */
    protected function prepareCompanyData(array $data): array
    {
        // Set default status if not provided
        if (!isset($data['status_id'])) {
            $data['status_id'] = \App\Models\Status::query()->where('type', 'company')->where('name', 'active')->value('id')
                ?? \App\Models\Status::query()->where('type', 'company')->first()?->id
                ?? \Database\Factories\CompanyFactory::companyStatusId('active');
        }

        // Set default is_active if not provided
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        // Set default company_type if not provided
        if (empty($data['company_type'])) {
            $data['company_type'] = \App\Enums\CompanyType::CLIENT->value;
        }

        // Set default customer_type if not provided for Client/Hybrid companies
        if (empty($data['customer_type'])) {
            $data['customer_type'] = \App\Enums\CustomerType::STANDARD->value;
        }

        // Set audit fields if authenticated user exists with valid UUID
        if (auth()->check()) {
            $userId = auth()->id();
            if (is_string($userId) && \Illuminate\Support\Str::isUuid($userId)) {
                $data['created_by'] = $userId;
                $data['updated_by'] = $userId;
            }
        }

        return $data;
    }
}
