<?php

declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * Submission baseline or operational actual values for variance evaluation (Phase 5.5b).
 */
readonly class ShipmentPriceVarianceInputs
{
    public function __construct(
        public ?float $distanceKm = null,
        public ?float $cargoWeight = null,
        public ?int $loadingWaitMinutes = null,
        public ?int $unloadingWaitMinutes = null,
    ) {}
}
