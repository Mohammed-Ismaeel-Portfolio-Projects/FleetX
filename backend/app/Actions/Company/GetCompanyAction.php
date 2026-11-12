<?php

namespace App\Actions\Company;

use App\Core\ApiResult;
use App\Exceptions\NotFoundException;
use App\Models\Company;

/**
 * Get Company Action
 * 
 * Contains ALL logic for retrieving a single company by UUID.
 * This action is responsible for:
 * - Retrieving company by UUID
 * - Throwing NotFoundException if company does not exist
 * - Returning the company
 * 
 * This action does NOT handle API responses or JSON formatting.
 * It returns an ApiResult object for the controller to format.
 */
class GetCompanyAction
{
    /**
     * Execute the action.
     * 
     * @param string $companyId UUID of the company
     * @return ApiResult
     */
    public function execute(string $companyId): ApiResult
    {
        try {
            // Retrieve company by UUID
            $company = Company::find($companyId);

            // Throw exception if company not found
            if (!$company) {
                throw new NotFoundException('Company not found');
            }

            // Return success result
            return ApiResult::success($company, 'Company retrieved successfully');
        } catch (NotFoundException $e) {
            // Re-throw NotFoundException for proper handling
            throw $e;
        } catch (\Exception $e) {
            // Return error result for other exceptions
            return ApiResult::error(
                'Failed to retrieve company: ' . $e->getMessage(),
                500
            );
        }
    }
}
