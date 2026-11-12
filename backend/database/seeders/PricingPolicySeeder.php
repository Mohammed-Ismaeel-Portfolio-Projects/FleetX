<?php

namespace Database\Seeders;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use App\Models\PricingBaseRate;
use App\Models\PricingDiscount;
use App\Models\PricingDistanceTier;
use App\Models\PricingFee;
use App\Models\PricingPolicy;
use App\Models\PricingPriorityMultiplier;
use App\Models\PricingServiceCatalog;
use App\Models\PricingVarianceRule;
use App\Models\PricingWaitingConfig;
use App\Models\PricingWeightConfig;
use App\Services\Pricing\Exceptions\NoPricingPolicyException;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\PricingPolicyValidator;
use App\Services\Pricing\PricingVarianceRuleValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Production bootstrap for a valid active PricingPolicy graph (PRICING-02).
 *
 * Idempotent: keyed by stable policy name. Does not create duplicate policies.
 * Does not touch the legacy pricing_rules table.
 *
 * Rate values match established test fixtures in SetsUpPricingForShipmentOrders.
 */
class PricingPolicySeeder extends Seeder
{
    public const POLICY_NAME = 'Standard Freight 2026';

    public function run(): void
    {
        DB::transaction(function (): void {
            $policy = $this->upsertPolicy();
            $this->upsertBaseRates($policy);
            $this->upsertDistanceTiers($policy);
            $this->upsertWeightConfig($policy);
            $this->upsertWaitingConfig($policy);
            $this->upsertFees($policy);
            $this->upsertPriorityMultipliers($policy);
            $this->upsertDiscounts($policy);
            $this->upsertServiceCatalog($policy);
            $this->upsertVarianceRules($policy);

            $policy->unsetRelations();
            $this->assertValid($policy);
        });
    }

    private function upsertPolicy(): PricingPolicy
    {
        $existing = PricingPolicy::query()->where('name', self::POLICY_NAME)->first();

        if ($existing !== null) {
            $existing->update([
                'description' => $existing->description ?: 'Default FLeetX freight pricing policy.',
                'currency' => $existing->currency ?: 'USD',
                'tax_rate' => $existing->tax_rate ?? 8.0000,
                'tax_inclusive' => false,
            ]);

            return $existing->refresh();
        }

        $status = $this->hasResolvableActivePolicy()
            ? PricingPolicyStatus::DRAFT
            : PricingPolicyStatus::ACTIVE;

        return PricingPolicy::create([
            'name' => self::POLICY_NAME,
            'description' => 'Default FLeetX freight pricing policy.',
            'status' => $status,
            'currency' => 'USD',
            'effective_from' => null,
            'effective_until' => null,
            'priority' => 0,
            'tax_rate' => 8.0000,
            'tax_inclusive' => false,
            'minimum_fare' => null,
            'created_by' => null,
        ]);
    }

    private function hasResolvableActivePolicy(): bool
    {
        try {
            app(PricingPolicyResolver::class)->resolveActive();

            return true;
        } catch (NoPricingPolicyException) {
            return false;
        }
    }

    private function upsertBaseRates(PricingPolicy $policy): void
    {
        $rates = [
            [CargoType::GENERAL, TrailerType::DRY_VAN, 1.45],
            [CargoType::REFRIGERATED, TrailerType::REEFER, 1.95],
            [CargoType::HEAVY, TrailerType::LOWBOY, 2.85],
            [CargoType::HAZARDOUS, TrailerType::TANKER, 2.50],
            [CargoType::FRAGILE, TrailerType::DRY_VAN, 1.55],
            [CargoType::GENERAL, null, 1.45],
            [CargoType::REFRIGERATED, null, 1.95],
            [CargoType::HEAVY, null, 2.85],
            [CargoType::HAZARDOUS, null, 2.50],
            [CargoType::FRAGILE, null, 1.55],
        ];

        foreach ($rates as [$cargoType, $trailerType, $ratePerKm]) {
            $query = PricingBaseRate::query()
                ->where('pricing_policy_id', $policy->id)
                ->where('cargo_type', $cargoType->value);

            if ($trailerType === null) {
                $query->whereNull('trailer_type');
            } else {
                $query->where('trailer_type', $trailerType->value);
            }

            $record = $query->first();

            if ($record === null) {
                PricingBaseRate::query()->create([
                    'pricing_policy_id' => $policy->id,
                    'cargo_type' => $cargoType,
                    'trailer_type' => $trailerType,
                    'rate_per_km' => $ratePerKm,
                ]);

                continue;
            }

            $record->update(['rate_per_km' => $ratePerKm]);
        }
    }

    private function upsertDistanceTiers(PricingPolicy $policy): void
    {
        $tiers = [
            [0, 100, 1.20],
            [101, 300, 0.95],
            [301, 700, 0.80],
            [701, null, 0.65],
        ];

        foreach ($tiers as [$min, $max, $rate]) {
            PricingDistanceTier::query()->updateOrCreate(
                [
                    'pricing_policy_id' => $policy->id,
                    'min_distance_km' => $min,
                ],
                [
                    'max_distance_km' => $max,
                    'rate_per_km' => $rate,
                ],
            );
        }
    }

    private function upsertWeightConfig(PricingPolicy $policy): void
    {
        PricingWeightConfig::query()->updateOrCreate(
            ['pricing_policy_id' => $policy->id],
            [
                'threshold_tons' => 20,
                'charge_per_ton' => 50,
            ],
        );
    }

    private function upsertWaitingConfig(PricingPolicy $policy): void
    {
        PricingWaitingConfig::query()->updateOrCreate(
            ['pricing_policy_id' => $policy->id],
            [
                'loading_free_minutes' => 30,
                'unloading_free_minutes' => 30,
                'rate_per_15min' => 15,
                'max_charge' => 200,
                'enabled' => true,
            ],
        );
    }

    private function upsertFees(PricingPolicy $policy): void
    {
        $fees = [
            ['border', 'Border Crossing', 'flat', 150],
            ['insurance', 'Insurance', 'percentage', 2],
            ['hazmat', 'Hazmat', 'percentage', 25],
            ['heavy_reefer', 'Heavy Reefer', 'percentage', 6],
        ];

        foreach ($fees as [$code, $name, $type, $amount]) {
            PricingFee::query()->updateOrCreate(
                [
                    'pricing_policy_id' => $policy->id,
                    'code' => $code,
                ],
                [
                    'name' => $name,
                    'fee_type' => $type,
                    'amount' => $amount,
                    'enabled' => true,
                ],
            );
        }
    }

    private function upsertPriorityMultipliers(PricingPolicy $policy): void
    {
        foreach (['standard' => 1.0, 'express' => 1.1, 'urgent' => 1.2, 'emergency' => 1.3] as $level => $multiplier) {
            PricingPriorityMultiplier::query()->updateOrCreate(
                [
                    'pricing_policy_id' => $policy->id,
                    'priority_level' => $level,
                ],
                ['multiplier' => $multiplier],
            );
        }
    }

    private function upsertDiscounts(PricingPolicy $policy): void
    {
        $discounts = [
            [CustomerType::CORPORATE->value, 'percentage', 5],
            [CustomerType::VIP->value, 'percentage', 10],
        ];

        foreach ($discounts as [$customerType, $type, $amount]) {
            PricingDiscount::query()->updateOrCreate(
                [
                    'pricing_policy_id' => $policy->id,
                    'customer_type' => $customerType,
                ],
                [
                    'discount_type' => $type,
                    'amount' => $amount,
                    'enabled' => true,
                ],
            );
        }
    }

    private function upsertServiceCatalog(PricingPolicy $policy): void
    {
        $services = [
            ['GPS Seal', 'GPS cargo seal', 15.00],
            ['Loading Crew', 'Loading labour', 250.00],
            ['Unloading Crew', 'Unloading labour', 250.00],
        ];

        foreach ($services as [$name, $description, $price]) {
            PricingServiceCatalog::query()->updateOrCreate(
                [
                    'pricing_policy_id' => $policy->id,
                    'name' => $name,
                ],
                [
                    'description' => $description,
                    'price' => $price,
                    'enabled' => true,
                ],
            );
        }
    }

    private function upsertVarianceRules(PricingPolicy $policy): void
    {
        $rules = [
            [VarianceFactorCode::DISTANCE, VarianceThresholdType::ABSOLUTE, 50],
            [VarianceFactorCode::WEIGHT, VarianceThresholdType::ABSOLUTE, 2],
            [VarianceFactorCode::WAITING_LOADING, VarianceThresholdType::ABSOLUTE, 15],
            [VarianceFactorCode::WAITING_UNLOADING, VarianceThresholdType::ABSOLUTE, 15],
        ];

        $varianceValidator = app(PricingVarianceRuleValidator::class);

        foreach ($rules as [$factor, $thresholdType, $thresholdValue]) {
            $candidate = new PricingVarianceRule([
                'factor_code' => $factor,
                'threshold_type' => $thresholdType,
                'threshold_value' => $thresholdValue,
                'applies_to_increase' => true,
                'applies_to_decrease' => false,
                'enabled' => true,
            ]);
            $varianceValidator->assertValid($candidate);

            PricingVarianceRule::query()->updateOrCreate(
                [
                    'pricing_policy_id' => $policy->id,
                    'factor_code' => $factor->value,
                ],
                [
                    'threshold_type' => $thresholdType->value,
                    'threshold_value' => $thresholdValue,
                    'applies_to_increase' => true,
                    'applies_to_decrease' => false,
                    'enabled' => true,
                ],
            );
        }
    }

    private function assertValid(PricingPolicy $policy): void
    {
        $policyValidator = app(PricingPolicyValidator::class);
        $rateErrors = $policyValidator->validateRateConfiguration($policy);

        if ($rateErrors !== []) {
            throw new \RuntimeException(
                'PricingPolicySeeder produced an invalid rate configuration: '.implode(' ', $rateErrors)
            );
        }
    }
}
