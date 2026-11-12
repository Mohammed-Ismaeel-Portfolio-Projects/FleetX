<?php

namespace App\Services\Pricing;

class FeeCalculationResult
{
    public function __construct(
        public readonly string $feeCode,
        public readonly string $feeType,
        public readonly float $amount,
        public readonly bool $triggered,
        public readonly bool $capped,
    ) {}

    public static function zero(string $feeCode): self
    {
        return new self(
            feeCode: $feeCode,
            feeType: 'none',
            amount: 0.0,
            triggered: false,
            capped: false,
        );
    }
}
