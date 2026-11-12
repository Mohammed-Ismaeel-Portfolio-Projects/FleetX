<?php

namespace App\Services\Pricing;

class MinimumFareResult
{
    public function __construct(
        public readonly float $originalAmount,
        public readonly ?float $minimumFare,
        public readonly float $adjustedAmount,
        public readonly bool $applied,
    ) {}
}
