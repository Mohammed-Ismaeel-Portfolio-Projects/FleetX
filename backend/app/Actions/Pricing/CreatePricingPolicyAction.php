<?php

declare(strict_types=1);

namespace App\Actions\Pricing;

use App\Core\ApiResult;
use App\Http\Resources\Admin\AdminPricingPolicyResource;
use App\Services\Pricing\Exceptions\PricingPolicyLifecycleException;
use App\Services\Pricing\PricingPolicyGovernanceService;

class CreatePricingPolicyAction
{
    public function __construct(
        private readonly PricingPolicyGovernanceService $governanceService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $createdBy): ApiResult
    {
        try {
            $policy = $this->governanceService->create($data, $createdBy);

            return ApiResult::success(
                (new AdminPricingPolicyResource($policy))->resolve(),
                'Pricing policy created successfully.',
            );
        } catch (PricingPolicyLifecycleException $e) {
            return ApiResult::error($e->getMessage(), $e->statusCode, $e->errors);
        }
    }
}
