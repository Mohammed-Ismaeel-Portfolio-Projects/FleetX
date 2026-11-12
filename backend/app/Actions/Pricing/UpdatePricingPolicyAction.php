<?php

declare(strict_types=1);

namespace App\Actions\Pricing;

use App\Core\ApiResult;
use App\Http\Resources\Admin\AdminPricingPolicyResource;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\PricingPolicyLifecycleException;
use App\Services\Pricing\PricingPolicyGovernanceService;

class UpdatePricingPolicyAction
{
    public function __construct(
        private readonly PricingPolicyGovernanceService $governanceService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PricingPolicy $policy, array $data): ApiResult
    {
        try {
            $updated = $this->governanceService->update($policy, $data);

            return ApiResult::success(
                (new AdminPricingPolicyResource($updated))->resolve(),
                'Pricing policy updated successfully.',
            );
        } catch (PricingPolicyLifecycleException $e) {
            return ApiResult::error($e->getMessage(), $e->statusCode, $e->errors);
        }
    }
}
