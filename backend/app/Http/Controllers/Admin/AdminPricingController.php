<?php

namespace App\Http\Controllers\Admin;

use App\Core\ApiResponse;
use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCalculatePricingRequest;
use App\Http\Resources\Admin\AdminPricingEstimateResource;
use App\Models\Company;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\MissingBaseRateException;
use App\Services\Pricing\Exceptions\MissingDistanceTierException;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use App\Services\Pricing\Exceptions\ServiceCatalogException;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingInputFactory;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\ServiceCatalogResolver;
use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use Illuminate\Http\JsonResponse;

class AdminPricingController extends Controller
{
    public function calculate(
        AdminCalculatePricingRequest $request,
        PricingPolicyResolver $policyResolver,
        TruckRouteDistanceCalculator $distanceCalculator,
        ServiceCatalogResolver $serviceCatalogResolver,
        PricingInputFactory $inputFactory,
        PricingEngine $pricingEngine,
    ): JsonResponse {
        $this->authorize('pricing.calculate');

        if ($request->hasOverrideFields() && ! $request->user()?->can('pricing.admin.calculate')) {
            return ApiResponse::forbidden('Pricing override permission is required.');
        }

        $customerTypeResult = $this->resolveCustomerType($request);
        if ($customerTypeResult instanceof JsonResponse) {
            return $customerTypeResult;
        }

        [$customerType, $customerTypeSource] = $customerTypeResult;

        $policyResult = $this->resolvePolicy($request, $policyResolver);
        if ($policyResult instanceof JsonResponse) {
            return $policyResult;
        }

        [$policy, $policySource] = $policyResult;

        $distanceResult = $this->resolveDistance($request, $distanceCalculator);
        if ($distanceResult instanceof JsonResponse) {
            return $distanceResult;
        }

        [
            'distance_km' => $distanceKm,
            'distance_source' => $distanceSource,
            'route_distance_km' => $routeDistanceKm,
            'manual_distance_km' => $manualDistanceKm,
        ] = $distanceResult;

        try {
            $resolvedServices = $serviceCatalogResolver->resolve(
                $policy,
                $request->validated('selected_service_ids', [])
            );
        } catch (ServiceCatalogException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $pricingInput = $inputFactory->create(
            distance: $distanceKm,
            cargoType: CargoType::from($request->validated('cargo_type')),
            trailerType: TrailerType::from($request->validated('trailer_type')),
            cargoWeight: (float) $request->validated('cargo_weight'),
            shipmentPriority: $request->validated('shipment_priority'),
            customerType: $customerType,
            resolvedServices: $resolvedServices,
            borderCrossing: (bool) $request->validated('border_crossing', false),
            hazmat: (bool) $request->validated('hazmat', false),
            insuranceRequired: (bool) $request->validated('insurance_required', false),
            loadingWaitingTime: (int) $request->validated('loading_waiting_time', 0),
            unloadingWaitingTime: (int) $request->validated('unloading_waiting_time', 0),
            trafficDelay: (int) $request->validated('traffic_delay', 0),
            customerDelay: (int) $request->validated('customer_delay', 0),
        );

        try {
            $breakdown = $pricingEngine->calculate($pricingInput, $policy);
        } catch (InvalidPricingInputException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (NoPricingPolicyException|InvalidPricingConfigException|MissingBaseRateException|MissingDistanceTierException $e) {
            return ApiResponse::error('Pricing configuration error. Please contact support.', 503);
        }

        return ApiResponse::success(
            new AdminPricingEstimateResource($breakdown, [
                'distance_km' => $distanceKm,
                'distance_source' => $distanceSource,
                'route_distance_km' => $routeDistanceKm,
                'manual_distance_km' => $manualDistanceKm,
                'policy_source' => $policySource,
                'customer_type_value' => $customerType->value,
                'customer_type_source' => $customerTypeSource,
            ]),
            'Pricing estimate calculated successfully.'
        );
    }

    /**
     * @return array{0: CustomerType, 1: string}|JsonResponse
     */
    private function resolveCustomerType(AdminCalculatePricingRequest $request): array|JsonResponse
    {
        if ($request->hasCustomerTypeOverride()) {
            return [CustomerType::from($request->validated('customer_type')), 'override'];
        }

        $company = $request->user()?->company;

        if ($company === null && $request->filled('customer_company_id')) {
            $company = Company::query()->find($request->validated('customer_company_id'));
        }

        if ($company === null) {
            return ApiResponse::error(
                'Customer company context is required to resolve customer type.',
                422
            );
        }

        $customerType = $company->customer_type;
        if (! $customerType instanceof CustomerType) {
            return ApiResponse::error(
                'Company customer type is not configured. Please contact support.',
                422
            );
        }

        return [$customerType, 'company'];
    }

    /**
     * @return array{0: PricingPolicy, 1: string}|JsonResponse
     */
    private function resolvePolicy(
        AdminCalculatePricingRequest $request,
        PricingPolicyResolver $policyResolver,
    ): array|JsonResponse {
        if ($request->hasPolicyOverride()) {
            $policy = PricingPolicy::query()->find($request->validated('pricing_policy_id'));

            if ($policy === null || ! $this->isPolicyActiveAtNow($policy)) {
                return ApiResponse::error('The selected pricing policy is not valid for override.', 422);
            }

            return [$policy, 'policy_override'];
        }

        try {
            return [$policyResolver->resolveActive(), 'active_resolved'];
        } catch (NoPricingPolicyException $e) {
            return ApiResponse::error('Pricing is temporarily unavailable.', 503);
        }
    }

    /**
     * @return array{
     *     distance_km: float,
     *     distance_source: string,
     *     route_distance_km: float|null,
     *     manual_distance_km: float|null,
     * }|JsonResponse
     */
    private function resolveDistance(
        AdminCalculatePricingRequest $request,
        TruckRouteDistanceCalculator $distanceCalculator,
    ): array|JsonResponse {
        $routeDistanceKm = null;
        $routingFailed = false;

        try {
            $routeDistanceKm = $distanceCalculator->calculate(
                (float) $request->validated('pickup_latitude'),
                (float) $request->validated('pickup_longitude'),
                (float) $request->validated('delivery_latitude'),
                (float) $request->validated('delivery_longitude'),
            );
        } catch (RoutingProviderUnavailableException) {
            $routingFailed = true;
        }

        if ($request->hasDistanceOverride()) {
            $manualDistanceKm = (float) $request->validated('distance_km');

            return [
                'distance_km' => $manualDistanceKm,
                'distance_source' => $routingFailed
                    ? 'manual_override_due_to_routing_failure'
                    : 'manual_override',
                'route_distance_km' => $routeDistanceKm,
                'manual_distance_km' => $manualDistanceKm,
            ];
        }

        if ($routingFailed || $routeDistanceKm === null) {
            return ApiResponse::error(
                'Route calculation is temporarily unavailable. Please try again later.',
                503
            );
        }

        return [
            'distance_km' => $routeDistanceKm,
            'distance_source' => 'route',
            'route_distance_km' => $routeDistanceKm,
            'manual_distance_km' => null,
        ];
    }

        private function isPolicyActiveAtNow(PricingPolicy $policy): bool
        {
            return app(PricingPolicyResolver::class)->isPolicyActiveAt($policy);
        }
}
