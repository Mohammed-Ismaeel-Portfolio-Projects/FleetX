<?php

namespace App\Services\Pricing;

class PrioritySurchargeResult
{
    public function __construct(
        public readonly string $priorityLevel,
        public readonly float $multiplier,
        public readonly float $surcharge,
    ) {}
}
