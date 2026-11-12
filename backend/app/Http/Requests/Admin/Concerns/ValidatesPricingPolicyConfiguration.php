<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;
use App\Enums\VarianceFactorCode;
use App\Enums\VarianceThresholdType;
use Illuminate\Validation\Rule;

trait ValidatesPricingPolicyConfiguration
{
    /**
     * @return array<string, mixed>
     */
    protected function pricingPolicyFieldRules(bool $requireAll = true): array
    {
        $required = $requireAll ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['prohibited'],
            'currency' => [$required, 'string', 'size:3'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'priority' => [$required, 'integer', 'min:0', 'max:9999'],
            'tax_rate' => [$required, 'numeric', 'min:0', 'max:100'],
            'tax_inclusive' => [$required, 'boolean'],
            'minimum_fare' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            'base_rates' => [$required, 'array', 'min:1'],
            'base_rates.*.cargo_type' => ['required', Rule::enum(CargoType::class)],
            'base_rates.*.trailer_type' => ['nullable', Rule::enum(TrailerType::class)],
            'base_rates.*.rate_per_km' => ['required', 'numeric', 'gt:0', 'max:9999.9999'],

            'distance_tiers' => [$required, 'array', 'min:1'],
            'distance_tiers.*.min_distance_km' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'distance_tiers.*.max_distance_km' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'distance_tiers.*.rate_per_km' => ['required', 'numeric', 'gt:0', 'max:9999.9999'],

            'weight_config' => ['nullable', 'array'],
            'weight_config.threshold_tons' => ['required_with:weight_config', 'numeric', 'min:0', 'max:999999.99'],
            'weight_config.charge_per_ton' => ['required_with:weight_config', 'numeric', 'min:0', 'max:999999.99'],

            'waiting_config' => ['nullable', 'array'],
            'waiting_config.loading_free_minutes' => ['required_with:waiting_config', 'integer', 'min:0', 'max:9999'],
            'waiting_config.unloading_free_minutes' => ['required_with:waiting_config', 'integer', 'min:0', 'max:9999'],
            'waiting_config.rate_per_15min' => ['required_with:waiting_config', 'numeric', 'min:0', 'max:999999.99'],
            'waiting_config.max_charge' => ['required_with:waiting_config', 'numeric', 'min:0', 'max:999999.99'],
            'waiting_config.enabled' => ['sometimes', 'boolean'],

            'fees' => ['sometimes', 'array'],
            'fees.*.code' => ['required', 'string', 'max:64'],
            'fees.*.name' => ['required', 'string', 'max:255'],
            'fees.*.fee_type' => ['required', 'string', Rule::in(['flat', 'percentage'])],
            'fees.*.amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'fees.*.condition_field' => ['nullable', 'string', 'max:64'],
            'fees.*.condition_value' => ['nullable', 'string', 'max:255'],
            'fees.*.max_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'fees.*.enabled' => ['sometimes', 'boolean'],

            'priority_multipliers' => ['sometimes', 'array'],
            'priority_multipliers.*.priority_level' => ['required', 'string', Rule::in(['standard', 'express', 'urgent', 'emergency'])],
            'priority_multipliers.*.multiplier' => ['required', 'numeric', 'gt:0', 'max:99.9999'],

            'discounts' => ['sometimes', 'array'],
            'discounts.*.customer_type' => ['required', Rule::enum(CustomerType::class)],
            'discounts.*.discount_type' => ['required', 'string', Rule::in(['flat', 'percentage'])],
            'discounts.*.amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'discounts.*.max_discount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'discounts.*.enabled' => ['sometimes', 'boolean'],

            'service_catalog' => ['sometimes', 'array'],
            'service_catalog.*.name' => ['required', 'string', 'max:255'],
            'service_catalog.*.description' => ['nullable', 'string', 'max:5000'],
            'service_catalog.*.price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'service_catalog.*.enabled' => ['sometimes', 'boolean'],

            'variance_rules' => ['sometimes', 'array'],
            'variance_rules.*.factor_code' => ['required', Rule::enum(VarianceFactorCode::class)],
            'variance_rules.*.threshold_type' => ['required', Rule::enum(VarianceThresholdType::class)],
            'variance_rules.*.threshold_value' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'variance_rules.*.applies_to_increase' => ['sometimes', 'boolean'],
            'variance_rules.*.applies_to_decrease' => ['sometimes', 'boolean'],
            'variance_rules.*.enabled' => ['sometimes', 'boolean'],
            'variance_rules.*.customer_message_template' => ['nullable', 'string', 'max:5000'],
            'variance_rules.*.internal_message_template' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pricingPolicyProhibitedFields(): array
    {
        return [
            'id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'total' => ['prohibited'],
            'final_total' => ['prohibited'],
            'breakdown_json' => ['prohibited'],
        ];
    }
}
