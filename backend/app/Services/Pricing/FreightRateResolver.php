<?php

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Enums\TrailerType;
use App\Models\PricingBaseRate;
use App\Models\PricingDistanceTier;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\MissingBaseRateException;
use App\Services\Pricing\Exceptions\MissingDistanceTierException;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;

class FreightRateResolver
{
    /**
     * Resolve freight rates from a specific policy for given parameters.
     *
     * @throws NoPricingPolicyException
     * @throws MissingBaseRateException
     * @throws MissingDistanceTierException
     */
    public function resolve(
        PricingPolicy $policy,
        CargoType $cargoType,
        TrailerType $trailerType,
        float $distance,
    ): FreightRateResolution {
        $baseRateResult = $this->resolveBaseRate($policy, $cargoType, $trailerType);
        $distanceTierResult = $this->resolveDistanceTier($policy, $distance);

        return new FreightRateResolution(
            baseRate: (float) $baseRateResult['rate']->rate_per_km,
            distanceTierRate: (float) $distanceTierResult->rate_per_km,
            matchedBaseRateId: $baseRateResult['rate']->id,
            matchedDistanceTierId: $distanceTierResult->id,
            baseRateResolutionType: $baseRateResult['type'],
            policyId: $policy->id,
        );
    }

    /**
     * Resolve base rate: exact match first, then cargo-only fallback.
     *
     * @return array{rate: PricingBaseRate, type: string}
     * @throws MissingBaseRateException
     */
    private function resolveBaseRate(
        PricingPolicy $policy,
        CargoType $cargoType,
        TrailerType $trailerType,
    ): array {
        // 1. Exact match: cargo_type + trailer_type
        $exact = $policy->baseRates()
            ->where('cargo_type', $cargoType->value)
            ->where('trailer_type', $trailerType->value)
            ->first();

        if ($exact) {
            return ['rate' => $exact, 'type' => 'exact'];
        }

        // 2. Cargo-only fallback (trailer_type IS NULL)
        $fallback = $policy->baseRates()
            ->where('cargo_type', $cargoType->value)
            ->whereNull('trailer_type')
            ->first();

        if ($fallback) {
            return ['rate' => $fallback, 'type' => 'cargo_fallback'];
        }

        throw new MissingBaseRateException(
            "No base rate configured for cargo type '{$cargoType->value}' "
            . "with trailer type '{$trailerType->value}' in policy #{$policy->id}."
        );
    }

    /**
     * Resolve the single distance tier that covers the given distance.
     *
     * @throws MissingDistanceTierException
     */
    private function resolveDistanceTier(PricingPolicy $policy, float $distance): PricingDistanceTier
    {
        $tier = $policy->distanceTiers()
            ->where('min_distance_km', '<=', $distance)
            ->where(function ($query) use ($distance) {
                $query->where('max_distance_km', '>=', $distance)
                    ->orWhereNull('max_distance_km');
            })
            ->first();

        if (!$tier) {
            throw new MissingDistanceTierException(
                "No distance tier covers {$distance} km in policy #{$policy->id}."
            );
        }

        return $tier;
    }
}
