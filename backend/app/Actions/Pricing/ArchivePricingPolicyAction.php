<?php

declare(strict_types=1);

namespace App\Actions\Pricing;

use App\Core\ApiResult;
use App\Http\Resources\Admin\AdminPricingPolicyResource;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\PricingPolicyLifecycleException;
use App\Services\Pricing\PricingPolicyGovernanceService;

class ArchivePricingPolicyAction
{
    public function __construct(
        private readonly PricingPolicyGovernanceService $governanceService,
    ) {}

    public function execute(PricingPolicy $policy): ApiResult
    {
        try {
            $archived = $this->governanceService->archive($policy);

            return ApiResult::success(
                (new AdminPricingPolicyResource($archived))->resolve(),
                'Pricing policy archived successfully.',
            );
        } catch (PricingPolicyLifecycleException $e) {
            return ApiResult::error($e->getMessage(), $e->statusCode, $e->errors);
        }
    }
}
