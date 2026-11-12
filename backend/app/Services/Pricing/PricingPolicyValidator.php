<?php

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Models\PricingPolicy;

class PricingPolicyValidator
{
    /**
     * Validate that a policy's rate configuration is complete and consistent.
     * Returns an array of error messages (empty = valid).
     *
     * @return string[]
     */
    public function validateRateConfiguration(PricingPolicy $policy): array
    {
        $errors = [];

        $errors = array_merge($errors, $this->validateBaseRates($policy));
        $errors = array_merge($errors, $this->validateDistanceTiers($policy));

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateBaseRates(PricingPolicy $policy): array
    {
        $errors = [];
        $rates = $policy->baseRates()->get();

        if ($rates->isEmpty()) {
            $errors[] = 'No base rates configured.';
            return $errors;
        }

        // Every cargo type must have at least one rate (exact or fallback)
        foreach (CargoType::cases() as $cargoType) {
            $hasRate = $rates->contains(fn ($rate) => $rate->cargo_type === $cargoType);
            if (!$hasRate) {
                $errors[] = "No base rate configured for cargo type '{$cargoType->value}'.";
            }
        }

        // Check for negative/zero rates
        foreach ($rates as $rate) {
            if ((float) $rate->rate_per_km <= 0) {
                $errors[] = "Base rate for '{$rate->cargo_type->value}/{$rate->trailer_type?->value}' must be > 0.";
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateDistanceTiers(PricingPolicy $policy): array
    {
        $errors = [];
        $tiers = $policy->distanceTiers()->orderBy('min_distance_km')->get();

        if ($tiers->isEmpty()) {
            $errors[] = 'No distance tiers configured.';
            return $errors;
        }

        // First tier must start at 0
        $first = $tiers->first();
        if ((float) $first->min_distance_km !== 0.0) {
            $errors[] = "First distance tier must start at 0 km (starts at {$first->min_distance_km}).";
        }

        // Last tier must be unbounded (max_distance_km = null)
        $last = $tiers->last();
        if ($last->max_distance_km !== null) {
            $errors[] = 'Last distance tier must have unlimited max (max_distance_km = null).';
        }

        // Check continuity, no overlaps, no gaps
        for ($i = 0; $i < $tiers->count() - 1; $i++) {
            $current = $tiers[$i];
            $next = $tiers[$i + 1];

            if ($current->max_distance_km === null) {
                $errors[] = "Only the last tier may have unlimited max. Tier starting at {$current->min_distance_km} has null max but is not last.";
                break;
            }

            $expectedNextMin = (float) $current->max_distance_km + 1;
            $actualNextMin = (float) $next->min_distance_km;

            if ($actualNextMin < $expectedNextMin) {
                $errors[] = "Distance tiers overlap: tier ending at {$current->max_distance_km} and tier starting at {$next->min_distance_km}.";
            } elseif ($actualNextMin > $expectedNextMin) {
                $errors[] = "Gap between distance tiers: {$current->max_distance_km} to {$next->min_distance_km}.";
            }
        }

        // Check rates > 0
        foreach ($tiers as $tier) {
            if ((float) $tier->rate_per_km <= 0) {
                $errors[] = "Distance tier [{$tier->min_distance_km}-{$tier->max_distance_km}] rate must be > 0.";
            }
        }

        // Check no negative distances
        foreach ($tiers as $tier) {
            if ((float) $tier->min_distance_km < 0) {
                $errors[] = "Distance tier has negative min_distance_km: {$tier->min_distance_km}.";
            }
            if ($tier->max_distance_km !== null && (float) $tier->max_distance_km < 0) {
                $errors[] = "Distance tier has negative max_distance_km: {$tier->max_distance_km}.";
            }
            if ($tier->max_distance_km !== null && (float) $tier->max_distance_km <= (float) $tier->min_distance_km) {
                $errors[] = "Distance tier has max <= min: [{$tier->min_distance_km}-{$tier->max_distance_km}].";
            }
        }

        return $errors;
    }
}
