<?php

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Enums\TrailerType;

class PricingInput
{
    /**
     * @param array<array{id: string, name: string, price: float}> $selectedServices
     */
    public function __construct(
        public readonly float $distance,
        public readonly CargoType $cargoType,
        public readonly TrailerType $trailerType,
        public readonly float $cargoWeight,
        public readonly string $shipmentPriority,
        public readonly string $customerType,
        public readonly bool $borderCrossing = false,
        public readonly bool $hazmat = false,
        public readonly bool $insuranceRequired = false,
        public readonly array $selectedServices = [],
        public readonly int $loadingWaitingTime = 0,
        public readonly int $unloadingWaitingTime = 0,
        public readonly int $trafficDelay = 0,
        public readonly int $customerDelay = 0,
    ) {}
}
