<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;
use App\Models\PricingPolicy;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\User;
use App\Services\Pricing\Exceptions\InvalidPricingConfigException;
use App\Services\Pricing\Exceptions\InvalidPricingInputException;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use App\Services\Pricing\Exceptions\ServiceCatalogException;
use App\Services\Pricing\Exceptions\ShipmentOrderPricingException;
use App\Services\Routing\TruckRouteDistanceCalculator;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use App\Support\ClientShipmentRequestCoordinateRules;

class ShipmentOrderPricingService
{
    public function __construct(
        private readonly PricingPolicyResolver $policyResolver,
        private readonly TruckRouteDistanceCalculator $distanceCalculator,
        private readonly ServiceCatalogResolver $serviceCatalogResolver,
        private readonly PricingInputFactory $inputFactory,
        private readonly PricingEngine $pricingEngine,
    ) {}

    /**
     * Calculate final pricing and persist an immutable snapshot for submission.
     *
     * @throws ShipmentOrderPricingException
     */
    public function calculateAndPersistSnapshot(
        ShipmentOrder $order,
        User $user,
        bool $replaceExisting = false,
    ): PricingBreakdown {
        $company = $user->company;
        if ($company === null) {
            throw new ShipmentOrderPricingException('User is not linked to a company.', 403);
        }

        $customerType = $company->customer_type;
        if (!$customerType instanceof CustomerType) {
            throw new ShipmentOrderPricingException(
                'Company customer type is not configured. Please contact support.',
                422
            );
        }

        $order->loadMissing('priceBreakdown');

        if ($order->priceBreakdown !== null && !$replaceExisting) {
            throw new ShipmentOrderPricingException(
                'A price snapshot already exists for this shipment request.',
                422
            );
        }

        try {
            $policy = $this->policyResolver->resolveActive();
        } catch (NoPricingPolicyException $e) {
            throw new ShipmentOrderPricingException('Pricing is temporarily unavailable.', 503);
        }

        $distance = $this->calculateDistance($order);
        $resolvedServices = $this->resolveServices($order, $policy);
        $pricingInput = $this->buildPricingInput($order, $customerType, $distance, $resolvedServices);

        try {
            $breakdown = $this->pricingEngine->calculate($pricingInput, $policy);
        } catch (InvalidPricingConfigException $e) {
            throw new ShipmentOrderPricingException(
                'Pricing configuration error. Please contact support.',
                503
            );
        } catch (InvalidPricingInputException $e) {
            throw new ShipmentOrderPricingException($e->getMessage(), 422);
        } catch (NoPricingPolicyException $e) {
            throw new ShipmentOrderPricingException('Pricing is temporarily unavailable.', 503);
        }

        $this->persistSnapshot($order, $policy, $breakdown, $replaceExisting);

        return $breakdown;
    }

    /**
     * @throws ShipmentOrderPricingException
     */
    private function calculateDistance(ShipmentOrder $order): float
    {
        $pickupLat = ClientShipmentRequestCoordinateRules::toFloat($order->pickup_latitude);
        $pickupLng = ClientShipmentRequestCoordinateRules::toFloat($order->pickup_longitude);
        $deliveryLat = ClientShipmentRequestCoordinateRules::toFloat($order->delivery_latitude);
        $deliveryLng = ClientShipmentRequestCoordinateRules::toFloat($order->delivery_longitude);

        if ($pickupLat === null || $pickupLng === null || $deliveryLat === null || $deliveryLng === null) {
            throw new ShipmentOrderPricingException(
                'Pickup and delivery coordinates are required for pricing.',
                422
            );
        }

        try {
            return $this->distanceCalculator->calculate(
                $pickupLat,
                $pickupLng,
                $deliveryLat,
                $deliveryLng,
            );
        } catch (RoutingProviderUnavailableException $e) {
            throw new ShipmentOrderPricingException(
                'Route calculation is temporarily unavailable. Please try again later.',
                503
            );
        }
    }

    /**
     * @return array<array{id: string, name: string, price: float}>
     *
     * @throws ShipmentOrderPricingException
     */
    private function resolveServices(ShipmentOrder $order, PricingPolicy $policy): array
    {
        $serviceIds = $order->selected_service_ids ?? [];

        try {
            return $this->serviceCatalogResolver->resolve($policy, $serviceIds);
        } catch (ServiceCatalogException $e) {
            throw new ShipmentOrderPricingException($e->getMessage(), 422);
        }
    }

    /**
     * @param array<array{id: string, name: string, price: float}> $resolvedServices
     *
     * @throws ShipmentOrderPricingException
     */
    private function buildPricingInput(
        ShipmentOrder $order,
        CustomerType $customerType,
        float $distance,
        array $resolvedServices,
    ): \App\Services\Pricing\PricingInput {
        $cargoType = CargoType::tryFrom((string) $order->cargo_type);
        if ($cargoType === null) {
            throw new ShipmentOrderPricingException(
                'Cargo type is not valid for pricing. Please update the shipment request.',
                422
            );
        }

        $trailerType = TrailerType::tryFrom((string) $order->trailer_type);
        if ($trailerType === null) {
            throw new ShipmentOrderPricingException(
                'Trailer type is required for pricing. Please update the shipment request.',
                422
            );
        }

        $shipmentPriority = $order->priority ?? 'standard';
        $validPriorities = ['standard', 'express', 'urgent', 'emergency'];
        if (!in_array($shipmentPriority, $validPriorities, true)) {
            throw new ShipmentOrderPricingException(
                'Shipment priority is not valid for pricing.',
                422
            );
        }

        return $this->inputFactory->create(
            distance: $distance,
            cargoType: $cargoType,
            trailerType: $trailerType,
            cargoWeight: (float) ($order->cargo_weight ?? 0),
            shipmentPriority: $shipmentPriority,
            customerType: $customerType,
            resolvedServices: $resolvedServices,
            borderCrossing: (bool) $order->border_crossing,
            hazmat: (bool) $order->hazardous_cargo,
            insuranceRequired: (bool) $order->insurance_required,
        );
    }

    private function persistSnapshot(
        ShipmentOrder $order,
        PricingPolicy $policy,
        PricingBreakdown $breakdown,
        bool $replaceExisting,
    ): void {
        $attributes = [
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => PricingBreakdownSerializer::toArray($breakdown),
            'final_total' => $breakdown->finalTotal,
            'currency' => $breakdown->currency,
            'calculated_at' => now(),
        ];

        if ($replaceExisting && $order->priceBreakdown !== null) {
            $order->priceBreakdown->update($attributes);

            return;
        }

        ShipmentOrderPriceBreakdown::create(array_merge($attributes, [
            'shipment_order_id' => $order->id,
        ]));
    }
}
