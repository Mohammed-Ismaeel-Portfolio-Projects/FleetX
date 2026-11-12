<?php

namespace App\Http\Controllers\Client;

use App\Core\ApiResponse;
use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CalculatePricingRequest;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use App\Services\Pricing\Exceptions\ServiceCatalogException;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingInputFactory;
use App\Services\Pricing\ServiceCatalogResolver;
use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Http\Resources\Client\ClientPricingEstimateResource;
use App\Services\Pricing\PricingPolicyResolver;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use Illuminate\Http\JsonResponse;

class ClientPricingController extends Controller
{
    public function calculate(
        CalculatePricingRequest $request,
        TruckRouteDistanceCalculator $distanceCalculator,
        ServiceCatalogResolver $serviceCatalogResolver,
        PricingInputFactory $inputFactory,
        PricingEngine $pricingEngine,
        PricingPolicyResolver $policyResolver,
    ): JsonResponse {
        $this->authorize('client.pricing.calculate');

        /** @var \App\Models\User $user */
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return ApiResponse::error('User is not linked to a company.', 403);
        }

        $customerType = $company->customer_type;
        if (!$customerType instanceof CustomerType) {
            return ApiResponse::error(
                'Company customer type is not configured. Please contact support.',
                422
            );
        }

        // Server-side distance calculation
        try {
            $distance = $distanceCalculator->calculate(
                $request->validated('pickup_latitude'),
                $request->validated('pickup_longitude'),
                $request->validated('delivery_latitude'),
                $request->validated('delivery_longitude'),
            );
        } catch (RoutingProviderUnavailableException $e) {
            return ApiResponse::error(
                'Route calculation is temporarily unavailable. Please try again later.',
                503
            );
        }

        // Resolve active pricing policy (single resolver source of truth)
        try {
            $policy = $policyResolver->resolveActive();
        } catch (NoPricingPolicyException) {
            return ApiResponse::error('Pricing is temporarily unavailable.', 503);
        }

        // Resolve services from catalog
        try {
            $resolvedServices = $serviceCatalogResolver->resolve(
                $policy,
                $request->validated('selected_service_ids', [])
            );
        } catch (ServiceCatalogException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        // Build trusted PricingInput
        $pricingInput = $inputFactory->create(
            distance: $distance,
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

        // Calculate pricing (pass resolved policy to avoid double-query)
        try {
            $breakdown = $pricingEngine->calculate($pricingInput, $policy);
        } catch (NoPricingPolicyException $e) {
            return ApiResponse::error('Pricing is temporarily unavailable.', 503);
        } catch (InvalidPricingConfigException $e) {
            return ApiResponse::error('Pricing configuration error. Please contact support.', 503);
        } catch (InvalidPricingInputException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            new ClientPricingEstimateResource($breakdown, $distance),
            'Pricing estimate calculated successfully.'
        );
    }
}
