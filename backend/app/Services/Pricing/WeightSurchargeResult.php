<?php

namespace App\Services\Pricing;

class WeightSurchargeResult
{
    public function __construct(
        public readonly float $cargoWeight,
        public readonly float $thresholdTons,
        public readonly float $excessWeight,
        public readonly float $chargePerTon,
        public readonly float $surcharge,
    ) {}
}
