<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Enums\PricingPolicyTemporalState;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use Carbon\CarbonInterface;

/**
 * Backend-authoritative temporal classification for pricing policies (PRICING-05).
 *
 * Uses the same effective-date semantics as PricingPolicyResolver.
 * Does not calculate prices.
 */
class PricingPolicyTemporalClassifier
{
    public function __construct(
        private readonly PricingPolicyResolver $policyResolver,
    ) {}

    public function classify(PricingPolicy $policy, ?CarbonInterface $at = null): PricingPolicyTemporalState
    {
        $at = $at ?? now();

        if ($policy->status === PricingPolicyStatus::ARCHIVED) {
            return PricingPolicyTemporalState::ARCHIVED;
        }

        if ($policy->status === PricingPolicyStatus::DRAFT) {
            return PricingPolicyTemporalState::DRAFT;
        }

        if ($policy->status !== PricingPolicyStatus::ACTIVE) {
            return PricingPolicyTemporalState::ARCHIVED;
        }

        if ($policy->effective_until !== null && $policy->effective_until->lte($at)) {
            return PricingPolicyTemporalState::EXPIRED;
        }

        if ($policy->effective_from !== null && $policy->effective_from->gt($at)) {
            return PricingPolicyTemporalState::FUTURE;
        }

        if ($this->isCurrentlyResolved($policy, $at)) {
            return PricingPolicyTemporalState::CURRENT;
        }

        return PricingPolicyTemporalState::ACTIVE_NOT_SELECTED;
    }

    public function isWithinEffectiveWindow(PricingPolicy $policy, ?CarbonInterface $at = null): bool
    {
        $at = $at ?? now();

        if ($policy->status !== PricingPolicyStatus::ACTIVE) {
            return false;
        }

        if ($policy->effective_from !== null && $policy->effective_from->gt($at)) {
            return false;
        }

        if ($policy->effective_until !== null && $policy->effective_until->lte($at)) {
            return false;
        }

        return true;
    }

    public function isCurrentlyResolved(PricingPolicy $policy, ?CarbonInterface $at = null): bool
    {
        if (! $this->isWithinEffectiveWindow($policy, $at)) {
            return false;
        }

        try {
            return $this->policyResolver->resolveActive($at)->is($policy);
        } catch (NoPricingPolicyException) {
            return false;
        }
    }
}
