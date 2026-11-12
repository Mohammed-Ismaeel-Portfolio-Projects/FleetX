<?php

namespace App\Services\Pricing;

class TaxCalculationResult
{
    public function __construct(
        public readonly float $afterDiscount,
        public readonly float $taxRate,
        public readonly float $taxAmount,
        public readonly float $totalWithTax,
    ) {}
}
