<?php

namespace App\Services\Pricing;

class WaitingChargeResult
{
    public function __construct(
        public readonly int $loadingWaitingTime,
        public readonly int $unloadingWaitingTime,
        public readonly int $trafficDelay,
        public readonly int $customerDelay,
        public readonly int $loadingExcessMinutes,
        public readonly int $unloadingExcessMinutes,
        public readonly float $loadingCharge,
        public readonly float $unloadingCharge,
        public readonly float $trafficCharge,
        public readonly float $customerCharge,
        public readonly float $totalBeforeCap,
        public readonly float $finalCharge,
        public readonly bool $capped,
        public readonly bool $enabled,
    ) {}
}
