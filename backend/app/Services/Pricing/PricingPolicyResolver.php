<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\PricingPolicyStatus;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class PricingPolicyResolver
{
    /**
     * @throws NoPricingPolicyException
     */
    public function resolveActive(?CarbonInterface $at = null): PricingPolicy
    {
        $policy = $this->activeAtQuery($at)->first();

        if (! $policy) {
            throw new NoPricingPolicyException('No active pricing policy found.');
        }

        return $policy;
    }

    /**
     * Policies eligible for resolution at a point in time, highest priority first.
     * Tie-break: highest id wins for deterministic selection (PRICING-05).
     */
    public function activeAtQuery(?CarbonInterface $at = null): Builder
    {
        $at = $at ?? now();

        return PricingPolicy::query()
            ->where('status', PricingPolicyStatus::ACTIVE)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $at);
            })
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    public function isPolicyActiveAt(PricingPolicy $policy, ?CarbonInterface $at = null): bool
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
}
