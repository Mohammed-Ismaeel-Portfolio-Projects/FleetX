<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\PricingPolicyTemporalState;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Admin timeline view over persisted pricing policies (PRICING-05).
 *
 * Uses PricingPolicyResolver as the single definition of "current".
 */
class PricingPolicyTimelineService
{
    public function __construct(
        private readonly PricingPolicyResolver $policyResolver,
        private readonly PricingPolicyTemporalClassifier $temporalClassifier,
    ) {}

    /**
     * @return array{
     *     current: array<string, mixed>|null,
     *     upcoming: list<array<string, mixed>>,
     *     history: list<array<string, mixed>>,
     *     drafts: list<array<string, mixed>>,
     *     other_active: list<array<string, mixed>>
     * }
     */
    public function build(?CarbonInterface $at = null): array
    {
        $at = $at ?? now();
        $policies = PricingPolicy::query()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $current = null;
        $upcoming = [];
        $history = [];
        $drafts = [];
        $otherActive = [];
        $resolved = null;

        try {
            $resolved = $this->policyResolver->resolveActive($at);
            $current = $this->summarize($resolved, $at);
        } catch (NoPricingPolicyException) {
            $current = null;
        }

        foreach ($policies as $policy) {
            if ($resolved !== null && $policy->is($resolved)) {
                continue;
            }

            $state = $this->temporalClassifier->classify($policy, $at);
            $entry = $this->summarize($policy, $at);

            match ($state) {
                PricingPolicyTemporalState::DRAFT => $drafts[] = $entry,
                PricingPolicyTemporalState::FUTURE => $upcoming[] = $entry,
                PricingPolicyTemporalState::ARCHIVED,
                PricingPolicyTemporalState::EXPIRED => $history[] = $entry,
                PricingPolicyTemporalState::ACTIVE_NOT_SELECTED => $otherActive[] = $entry,
                PricingPolicyTemporalState::CURRENT => null,
            };
        }

        return [
            'current' => $current,
            'upcoming' => $upcoming,
            'history' => $history,
            'drafts' => $drafts,
            'other_active' => $otherActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(PricingPolicy $policy, CarbonInterface $at): array
    {
        return [
            'id' => $policy->id,
            'name' => $policy->name,
            'description' => $policy->description,
            'status' => $policy->status?->value,
            'temporal_state' => $this->temporalClassifier->classify($policy, $at)->value,
            'priority' => $policy->priority,
            'currency' => $policy->currency,
            'effective_from' => $policy->effective_from?->format('Y-m-d H:i:s'),
            'effective_until' => $policy->effective_until?->format('Y-m-d H:i:s'),
            'tax_rate' => $policy->tax_rate !== null ? (float) $policy->tax_rate : null,
            'tax_inclusive' => (bool) $policy->tax_inclusive,
            'minimum_fare' => $policy->minimum_fare !== null ? (float) $policy->minimum_fare : null,
            'created_at' => $policy->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $policy->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
