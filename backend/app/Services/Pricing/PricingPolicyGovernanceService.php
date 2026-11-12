<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\CargoType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\PricingPolicy;
use App\Models\PricingVarianceRule;
use App\Services\Pricing\Exceptions\PricingPolicyLifecycleException;
use Illuminate\Support\Facades\DB;

/**
 * Admin pricing policy aggregate persistence and lifecycle (PRICING-04).
 *
 * Validates and persists configuration only. Does not calculate shipment prices.
 */
class PricingPolicyGovernanceService
{
    public function __construct(
        private readonly PricingPolicyValidator $policyValidator,
        private readonly PricingVarianceRuleValidator $varianceRuleValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $createdBy): PricingPolicy
    {
        return DB::transaction(function () use ($data, $createdBy): PricingPolicy {
            $policy = PricingPolicy::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => PricingPolicyStatus::DRAFT,
                'currency' => $data['currency'] ?? 'USD',
                'effective_from' => $data['effective_from'] ?? null,
                'effective_until' => $data['effective_until'] ?? null,
                'priority' => (int) ($data['priority'] ?? 0),
                'tax_rate' => $data['tax_rate'] ?? 0,
                'tax_inclusive' => (bool) ($data['tax_inclusive'] ?? false),
                'minimum_fare' => $data['minimum_fare'] ?? null,
                'created_by' => $createdBy,
            ]);

            $this->syncRuleGraph($policy, $data);

            return $this->reloadWithRules($policy);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PricingPolicy $policy, array $data): PricingPolicy
    {
        $this->assertMutable($policy);

        return DB::transaction(function () use ($policy, $data): PricingPolicy {
            $policy->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'currency' => $data['currency'] ?? $policy->currency,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_until' => $data['effective_until'] ?? null,
                'priority' => (int) ($data['priority'] ?? $policy->priority),
                'tax_rate' => $data['tax_rate'] ?? $policy->tax_rate,
                'tax_inclusive' => (bool) ($data['tax_inclusive'] ?? $policy->tax_inclusive),
                'minimum_fare' => array_key_exists('minimum_fare', $data) ? $data['minimum_fare'] : $policy->minimum_fare,
            ]);

            $this->syncRuleGraph($policy->refresh(), $data);

            return $this->reloadWithRules($policy);
        });
    }

    public function activate(PricingPolicy $policy): PricingPolicy
    {
        if ($policy->status === PricingPolicyStatus::ACTIVE) {
            return $this->reloadWithRules($policy);
        }

        if ($policy->status !== PricingPolicyStatus::DRAFT) {
            throw new PricingPolicyLifecycleException(
                'Only draft pricing policies can be activated.',
                422,
                ['status' => ['Invalid lifecycle transition to active.']],
            );
        }

        $this->assertActivationReady($policy);

        return DB::transaction(function () use ($policy): PricingPolicy {
            $policy->status = PricingPolicyStatus::ACTIVE;
            $policy->save();

            return $this->reloadWithRules($policy);
        });
    }

    public function archive(PricingPolicy $policy): PricingPolicy
    {
        if ($policy->status === PricingPolicyStatus::ARCHIVED) {
            throw new PricingPolicyLifecycleException(
                'Pricing policy is already archived.',
                422,
                ['status' => ['Policy is already archived.']],
            );
        }

        if (! in_array($policy->status, [PricingPolicyStatus::DRAFT, PricingPolicyStatus::ACTIVE], true)) {
            throw new PricingPolicyLifecycleException(
                'Pricing policy cannot be archived from its current status.',
                422,
                ['status' => ['Invalid lifecycle transition to archived.']],
            );
        }

        return DB::transaction(function () use ($policy): PricingPolicy {
            $policy->status = PricingPolicyStatus::ARCHIVED;
            $policy->save();

            return $this->reloadWithRules($policy);
        });
    }

    private function assertMutable(PricingPolicy $policy): void
    {
        if ($policy->status !== PricingPolicyStatus::DRAFT) {
            throw new PricingPolicyLifecycleException(
                'Only draft pricing policies can be modified.',
                422,
                ['status' => ['Active and archived policies are immutable.']],
            );
        }
    }

    private function assertActivationReady(PricingPolicy $policy): void
    {
        $policy->load([
            'baseRates',
            'distanceTiers',
            'weightConfig',
            'waitingConfig',
            'fees',
            'priorityMultipliers',
            'discounts',
            'serviceCatalogItems',
            'varianceRules',
        ]);

        $errors = [];

        if ($policy->effective_from !== null
            && $policy->effective_until !== null
            && $policy->effective_until->lte($policy->effective_from)) {
            $errors['effective_until'][] = 'Effective until must be after effective from.';
        }

        $rateErrors = $this->policyValidator->validateRateConfiguration($policy);
        foreach ($rateErrors as $message) {
            $errors['configuration'][] = $message;
        }

        foreach ($policy->varianceRules as $rule) {
            $ruleErrors = $this->varianceRuleValidator->validate($rule);
            foreach ($ruleErrors as $message) {
                $errors['variance_rules'][] = $message;
            }
        }

        if ($errors !== []) {
            throw new PricingPolicyLifecycleException(
                'Pricing policy configuration is invalid and cannot be activated.',
                422,
                $errors,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRuleGraph(PricingPolicy $policy, array $data): void
    {
        try {
            $this->persistRuleGraph($policy, $data);
        } catch (\Illuminate\Database\QueryException) {
            throw new PricingPolicyLifecycleException(
                'Pricing policy configuration could not be persisted.',
                422,
                ['configuration' => ['Invalid or duplicate rule configuration.']],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRuleGraph(PricingPolicy $policy, array $data): void
    {
        $policy->baseRates()->delete();
        foreach ($data['base_rates'] ?? [] as $rate) {
            $policy->baseRates()->create([
                'cargo_type' => CargoType::from($rate['cargo_type']),
                'trailer_type' => isset($rate['trailer_type']) && $rate['trailer_type'] !== null
                    ? TrailerType::from($rate['trailer_type'])
                    : null,
                'rate_per_km' => $rate['rate_per_km'],
            ]);
        }

        $policy->distanceTiers()->delete();
        foreach ($data['distance_tiers'] ?? [] as $tier) {
            $policy->distanceTiers()->create([
                'min_distance_km' => $tier['min_distance_km'],
                'max_distance_km' => $tier['max_distance_km'] ?? null,
                'rate_per_km' => $tier['rate_per_km'],
            ]);
        }

        $policy->weightConfig()->delete();
        if (! empty($data['weight_config'])) {
            $policy->weightConfig()->create([
                'threshold_tons' => $data['weight_config']['threshold_tons'],
                'charge_per_ton' => $data['weight_config']['charge_per_ton'],
            ]);
        }

        $policy->waitingConfig()->delete();
        if (! empty($data['waiting_config'])) {
            $policy->waitingConfig()->create([
                'loading_free_minutes' => $data['waiting_config']['loading_free_minutes'],
                'unloading_free_minutes' => $data['waiting_config']['unloading_free_minutes'],
                'rate_per_15min' => $data['waiting_config']['rate_per_15min'],
                'max_charge' => $data['waiting_config']['max_charge'],
                'enabled' => (bool) ($data['waiting_config']['enabled'] ?? true),
            ]);
        }

        $policy->fees()->delete();
        foreach ($data['fees'] ?? [] as $fee) {
            $policy->fees()->create([
                'code' => $fee['code'],
                'name' => $fee['name'],
                'fee_type' => $fee['fee_type'],
                'amount' => $fee['amount'],
                'condition_field' => $fee['condition_field'] ?? null,
                'condition_value' => $fee['condition_value'] ?? null,
                'max_amount' => $fee['max_amount'] ?? null,
                'enabled' => (bool) ($fee['enabled'] ?? true),
            ]);
        }

        $policy->priorityMultipliers()->delete();
        foreach ($data['priority_multipliers'] ?? [] as $multiplier) {
            $policy->priorityMultipliers()->create([
                'priority_level' => $multiplier['priority_level'],
                'multiplier' => $multiplier['multiplier'],
            ]);
        }

        $policy->discounts()->delete();
        foreach ($data['discounts'] ?? [] as $discount) {
            $policy->discounts()->create([
                'customer_type' => $discount['customer_type'],
                'discount_type' => $discount['discount_type'],
                'amount' => $discount['amount'],
                'max_discount' => $discount['max_discount'] ?? null,
                'enabled' => (bool) ($discount['enabled'] ?? true),
            ]);
        }

        $policy->serviceCatalogItems()->delete();
        foreach ($data['service_catalog'] ?? [] as $service) {
            $policy->serviceCatalogItems()->create([
                'name' => $service['name'],
                'description' => $service['description'] ?? null,
                'price' => $service['price'],
                'enabled' => (bool) ($service['enabled'] ?? true),
            ]);
        }

        $policy->varianceRules()->delete();
        foreach ($data['variance_rules'] ?? [] as $ruleData) {
            $candidate = new PricingVarianceRule([
                'factor_code' => VarianceFactorCode::from($ruleData['factor_code']),
                'threshold_type' => VarianceThresholdType::from($ruleData['threshold_type']),
                'threshold_value' => $ruleData['threshold_value'],
                'applies_to_increase' => (bool) ($ruleData['applies_to_increase'] ?? true),
                'applies_to_decrease' => (bool) ($ruleData['applies_to_decrease'] ?? false),
                'enabled' => (bool) ($ruleData['enabled'] ?? true),
                'customer_message_template' => $ruleData['customer_message_template'] ?? null,
                'internal_message_template' => $ruleData['internal_message_template'] ?? null,
            ]);

            try {
                $this->varianceRuleValidator->assertValid($candidate);
            } catch (\InvalidArgumentException $e) {
                throw new PricingPolicyLifecycleException(
                    $e->getMessage(),
                    422,
                    ['variance_rules' => [$e->getMessage()]],
                );
            }

            $policy->varianceRules()->create([
                'factor_code' => $ruleData['factor_code'],
                'threshold_type' => $ruleData['threshold_type'],
                'threshold_value' => $ruleData['threshold_value'],
                'applies_to_increase' => (bool) ($ruleData['applies_to_increase'] ?? true),
                'applies_to_decrease' => (bool) ($ruleData['applies_to_decrease'] ?? false),
                'enabled' => (bool) ($ruleData['enabled'] ?? true),
                'customer_message_template' => $ruleData['customer_message_template'] ?? null,
                'internal_message_template' => $ruleData['internal_message_template'] ?? null,
            ]);
        }
    }

    private function reloadWithRules(PricingPolicy $policy): PricingPolicy
    {
        return $policy->fresh([
            'baseRates',
            'distanceTiers',
            'weightConfig',
            'waitingConfig',
            'fees',
            'priorityMultipliers',
            'discounts',
            'serviceCatalogItems',
            'varianceRules',
        ]);
    }
}
