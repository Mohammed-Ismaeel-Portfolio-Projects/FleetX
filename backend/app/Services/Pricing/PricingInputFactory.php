<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;

class PricingInputFactory
{
    /**
     * Build a PricingInput from server-resolved, trusted data.
     *
     * @param float $distance Server-calculated distance (from routing service)
     * @param CargoType $cargoType Validated cargo type
     * @param TrailerType $trailerType Validated trailer type
     * @param float $cargoWeight Validated cargo weight in tons
     * @param string $shipmentPriority Validated priority level
     * @param CustomerType $customerType Server-resolved from authenticated user's company
     * @param array<array{id: string, name: string, price: float}> $resolvedServices Output from ServiceCatalogResolver
     * @param bool $borderCrossing Whether shipment crosses a border
     * @param bool $hazmat Whether cargo is hazardous
     * @param bool $insuranceRequired Whether insurance is requested
     * @param int $loadingWaitingTime Estimated loading wait (minutes)
     * @param int $unloadingWaitingTime Estimated unloading wait (minutes)
     * @param int $trafficDelay Estimated traffic delay (minutes)
     * @param int $customerDelay Estimated customer delay (minutes)
     */
    public function create(
        float $distance,
        CargoType $cargoType,
        TrailerType $trailerType,
        float $cargoWeight,
        string $shipmentPriority,
        CustomerType $customerType,
        array $resolvedServices = [],
        bool $borderCrossing = false,
        bool $hazmat = false,
        bool $insuranceRequired = false,
        int $loadingWaitingTime = 0,
        int $unloadingWaitingTime = 0,
        int $trafficDelay = 0,
        int $customerDelay = 0,
    ): PricingInput {
        return new PricingInput(
            distance: $distance,
            cargoType: $cargoType,
            trailerType: $trailerType,
            cargoWeight: $cargoWeight,
            shipmentPriority: $shipmentPriority,
            customerType: $customerType->value,
            borderCrossing: $borderCrossing,
            hazmat: $hazmat,
            insuranceRequired: $insuranceRequired,
            selectedServices: $resolvedServices,
            loadingWaitingTime: $loadingWaitingTime,
            unloadingWaitingTime: $unloadingWaitingTime,
            trafficDelay: $trafficDelay,
            customerDelay: $customerDelay,
        );
    }
}
