<?php

namespace App\Services\Pricing;

class FreightRateResolution
{
    public function __construct(
        public readonly float $baseRate,
        public readonly float $distanceTierRate,
        public readonly int $matchedBaseRateId,
        public readonly int $matchedDistanceTierId,
        public readonly string $baseRateResolutionType, // 'exact' or 'cargo_fallback'
        public readonly int $policyId,
    ) {}
}
