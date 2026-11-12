<?php

namespace App\Http\Controllers;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\Company\DeleteCompanyAction;
use App\Actions\Company\GetCompanyAction;
use App\Actions\Company\QueryCompaniesAction;
use App\Actions\Company\RestoreCompanyAction;
use App\Actions\Company\ToggleCompanyStatusAction;
use App\Actions\Company\UpdateCompanyAction;
use App\Core\ApiResponse;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\QueryCompaniesRequest;
use App\Http\Requests\Company\ToggleCompanyStatusRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyCollection;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * Company Controller
 * 
 * Handles HTTP requests for company operations.
 * This controller is lightweight - it only handles request/response concerns.
 * All business logic is delegated to Actions.
 */
class CompanyController extends Controller
{
    /**
     * List companies.
     * 
     * @param QueryCompaniesRequest $request
     * @param QueryCompaniesAction $action
     * @return JsonResponse
     */
    public function index(QueryCompaniesRequest $request, QueryCompaniesAction $action): JsonResponse
    {
        $this->authorize('view', Company::class);

        // Execute the action with validated filters
        $result = $action->execute($request->validated());

        // Return appropriate API response
        if ($result->success) {
            return ApiResponse::success(
                new CompanyCollection($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    /**
     * Get company details.
     * 
     * @param Company $company Route model binding
     * @param GetCompanyAction $action
     * @return JsonResponse
     */
    public function show(Company $company, GetCompanyAction $action): JsonResponse
    {
        $this->authorize('show', $company);

        // Execute the action with company UUID
        $result = $action->execute($company->id);

        // Return appropriate API response
        if ($result->success) {
            return ApiResponse::success(
                new CompanyResource($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    /**
     * Create a new company.
     * 
     * @param CreateCompanyRequest $request
     * @param CreateCompanyAction $action
     * @return JsonResponse
     */
    public function store(CreateCompanyRequest $request, CreateCompanyAction $action): JsonResponse
    {
        $this->authorize('create', Company::class);

        // Execute the action with validated data
        $result = $action->execute($request->validated());

        // Return appropriate API response
        if ($result->success) {
            return ApiResponse::success(
                new CompanyResource($result->data),
                $result->message,
                201
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    /**
     * Update a company.
     * 
     * @param Company $company Route model binding
     * @param UpdateCompanyRequest $request
     * @param UpdateCompanyAction $action
     * @return JsonResponse
     */
    public function update(Company $company, UpdateCompanyRequest $request, UpdateCompanyAction $action): JsonResponse
    {
        $this->authorize('update', $company);

        // Execute the action with company and validated data
        $result = $action->execute($company, $request->validated());

        // Return appropriate API response
        if ($result->success) {
            return ApiResponse::success(
                new CompanyResource($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    /**
     * Toggle company active status.
     * 
     * @param Company $company Route model binding
     * @param ToggleCompanyStatusRequest $request
     * @param ToggleCompanyStatusAction $action
     * @return JsonResponse
     */
    public function toggleStatus(Company $company, ToggleCompanyStatusRequest $request, ToggleCompanyStatusAction $action): JsonResponse
    {
        $this->authorize('toggleStatus', $company);

        // Execute the action with company and validated status
        $result = $action->execute($company, $request->validated()['is_active']);

        // Return appropriate API response
        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    /**
     * Delete a company.
     * 
     * @param Company $company Route model binding
     * @param DeleteCompanyAction $action
     * @return JsonResponse
     */
    public function destroy(Company $company, DeleteCompanyAction $action): JsonResponse
    {
        $this->authorize('delete', $company);

        // Execute the action with company
        $result = $action->execute($company);

        // Return appropriate API response
        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    /**
     * Restore a soft-deleted company.
     * 
     * @param string $company UUID of the company to restore
     * @param RestoreCompanyAction $action
     * @return JsonResponse
     */
    public function restore(string $company, RestoreCompanyAction $action): JsonResponse
    {
        $this->authorize('restore');

        // Execute the action with company UUID
        $result = $action->execute($company);

        // Return appropriate API response
        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }
}
