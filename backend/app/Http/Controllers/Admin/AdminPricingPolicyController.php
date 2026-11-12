<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Pricing\ActivatePricingPolicyAction;
use App\Actions\Pricing\ArchivePricingPolicyAction;
use App\Actions\Pricing\CreatePricingPolicyAction;
use App\Actions\Pricing\UpdatePricingPolicyAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePricingPolicyRequest;
use App\Http\Requests\Admin\UpdatePricingPolicyRequest;
use App\Http\Resources\Admin\AdminPricingPolicyResource;
use App\Http\Resources\Admin\AdminPricingPolicySummaryResource;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\PricingPolicyTemporalClassifier;
use App\Services\Pricing\PricingPolicyTimelineService;
use Illuminate\Http\JsonResponse;

/**
 * Admin Pricing Policy governance API (PRICING-02 read, PRICING-04 mutation).
 *
 * Does not calculate prices or mutate operational records.
 */
class AdminPricingPolicyController extends Controller
{
    /**
     * @var list<string>
     */
    public const RULE_RELATIONS = [
        'baseRates',
        'distanceTiers',
        'weightConfig',
        'waitingConfig',
        'fees',
        'priorityMultipliers',
        'discounts',
        'serviceCatalogItems',
        'varianceRules',
    ];

    public function index(PricingPolicyTemporalClassifier $temporalClassifier): JsonResponse
    {
        $this->authorize('pricing.policy.viewAny');

        $policies = PricingPolicy::query()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            $policies->map(function (PricingPolicy $policy) use ($temporalClassifier) {
                $summary = (new AdminPricingPolicySummaryResource($policy))->resolve();
                $summary['temporal_state'] = $temporalClassifier->classify($policy)->value;

                return $summary;
            })->all(),
            'Pricing policies retrieved successfully.',
        );
    }

    public function show(PricingPolicy $pricingPolicy): JsonResponse
    {
        $this->authorize('pricing.policy.view');

        $pricingPolicy->load(self::RULE_RELATIONS);

        return ApiResponse::success(
            (new AdminPricingPolicyResource($pricingPolicy))->resolve(),
            'Pricing policy retrieved successfully.',
        );
    }

    public function active(PricingPolicyResolver $policyResolver): JsonResponse
    {
        $this->authorize('pricing.policy.view');

        try {
            $policy = $policyResolver->resolveActive();
        } catch (NoPricingPolicyException) {
            return ApiResponse::notFound('No active pricing policy is currently in effect.');
        }

        $policy->load(self::RULE_RELATIONS);

        $data = (new AdminPricingPolicyResource($policy))->resolve();
        $data['temporal_state'] = 'current';

        return ApiResponse::success(
            $data,
            'Active pricing policy retrieved successfully.',
        );
    }

    public function timeline(PricingPolicyTimelineService $timelineService): JsonResponse
    {
        $this->authorize('pricing.policy.viewAny');

        return ApiResponse::success(
            $timelineService->build(),
            'Pricing policy timeline retrieved successfully.',
        );
    }

    public function store(
        StorePricingPolicyRequest $request,
        CreatePricingPolicyAction $action,
    ): JsonResponse {
        $this->authorize('pricing.policy.create');

        $result = $action->execute(
            $request->validated(),
            $request->user()?->id,
        );

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message, 201);
    }

    public function update(
        PricingPolicy $pricingPolicy,
        UpdatePricingPolicyRequest $request,
        UpdatePricingPolicyAction $action,
    ): JsonResponse {
        $this->authorize('pricing.policy.update');

        $result = $action->execute($pricingPolicy, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function activate(
        PricingPolicy $pricingPolicy,
        ActivatePricingPolicyAction $action,
    ): JsonResponse {
        $this->authorize('pricing.policy.activate');

        $result = $action->execute($pricingPolicy);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function archive(
        PricingPolicy $pricingPolicy,
        ArchivePricingPolicyAction $action,
    ): JsonResponse {
        $this->authorize('pricing.policy.archive');

        $result = $action->execute($pricingPolicy);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
