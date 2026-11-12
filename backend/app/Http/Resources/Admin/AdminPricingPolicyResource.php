<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin pricing policy detail with nested rule configuration (PRICING-02).
 *
 * Source of truth: persisted PricingPolicy graph. No calculation.
 */
class AdminPricingPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from?->format('Y-m-d H:i:s'),
            'effective_until' => $this->effective_until?->format('Y-m-d H:i:s'),
            'tax_rate' => $this->numeric($this->tax_rate),
            'tax_inclusive' => (bool) $this->tax_inclusive,
            'minimum_fare' => $this->numeric($this->minimum_fare),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            'base_rates' => $this->whenLoaded('baseRates', fn () => $this->baseRates
                ->sortBy(fn ($rate) => sprintf(
                    '%s|%s|%d',
                    $rate->cargo_type?->value ?? '',
                    $rate->trailer_type?->value ?? '',
                    $rate->id,
                ))
                ->values()
                ->map(fn ($rate) => [
                    'id' => $rate->id,
                    'cargo_type' => $rate->cargo_type?->value,
                    'trailer_type' => $rate->trailer_type?->value,
                    'rate_per_km' => $this->numeric($rate->rate_per_km),
                ])
                ->all()),

            'distance_tiers' => $this->whenLoaded('distanceTiers', fn () => $this->distanceTiers
                ->sortBy(fn ($tier) => [(float) $tier->min_distance_km, $tier->id])
                ->values()
                ->map(fn ($tier) => [
                    'id' => $tier->id,
                    'min_distance_km' => $this->numeric($tier->min_distance_km),
                    'max_distance_km' => $this->numeric($tier->max_distance_km),
                    'rate_per_km' => $this->numeric($tier->rate_per_km),
                ])
                ->all()),

            'weight_config' => $this->whenLoaded('weightConfig', fn () => $this->weightConfig === null
                ? null
                : [
                    'id' => $this->weightConfig->id,
                    'threshold_tons' => $this->numeric($this->weightConfig->threshold_tons),
                    'charge_per_ton' => $this->numeric($this->weightConfig->charge_per_ton),
                ]),

            'waiting_config' => $this->whenLoaded('waitingConfig', fn () => $this->waitingConfig === null
                ? null
                : [
                    'id' => $this->waitingConfig->id,
                    'loading_free_minutes' => $this->waitingConfig->loading_free_minutes,
                    'unloading_free_minutes' => $this->waitingConfig->unloading_free_minutes,
                    'rate_per_15min' => $this->numeric($this->waitingConfig->rate_per_15min),
                    'max_charge' => $this->numeric($this->waitingConfig->max_charge),
                    'enabled' => (bool) $this->waitingConfig->enabled,
                ]),

            'fees' => $this->whenLoaded('fees', fn () => $this->fees
                ->sortBy(fn ($fee) => [$fee->code, $fee->id])
                ->values()
                ->map(fn ($fee) => [
                    'id' => $fee->id,
                    'code' => $fee->code,
                    'name' => $fee->name,
                    'fee_type' => $fee->fee_type,
                    'amount' => $this->numeric($fee->amount),
                    'condition_field' => $fee->condition_field,
                    'condition_value' => $fee->condition_value,
                    'max_amount' => $this->numeric($fee->max_amount),
                    'enabled' => (bool) $fee->enabled,
                ])
                ->all()),

            'priority_multipliers' => $this->whenLoaded('priorityMultipliers', fn () => $this->priorityMultipliers
                ->sortBy(fn ($multiplier) => [$multiplier->priority_level, $multiplier->id])
                ->values()
                ->map(fn ($multiplier) => [
                    'id' => $multiplier->id,
                    'priority_level' => $multiplier->priority_level,
                    'multiplier' => $this->numeric($multiplier->multiplier),
                ])
                ->all()),

            'discounts' => $this->whenLoaded('discounts', fn () => $this->discounts
                ->sortBy(fn ($discount) => [$discount->customer_type, $discount->id])
                ->values()
                ->map(fn ($discount) => [
                    'id' => $discount->id,
                    'customer_type' => $discount->customer_type,
                    'discount_type' => $discount->discount_type,
                    'amount' => $this->numeric($discount->amount),
                    'max_discount' => $this->numeric($discount->max_discount),
                    'enabled' => (bool) $discount->enabled,
                ])
                ->all()),

            'service_catalog' => $this->whenLoaded('serviceCatalogItems', fn () => $this->serviceCatalogItems
                ->sortBy(fn ($service) => [$service->name, $service->id])
                ->values()
                ->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $this->numeric($service->price),
                    'enabled' => (bool) $service->enabled,
                ])
                ->all()),

            'variance_rules' => $this->whenLoaded('varianceRules', fn () => $this->varianceRules
                ->sortBy(fn ($rule) => [$rule->factor_code?->value ?? '', $rule->id])
                ->values()
                ->map(fn ($rule) => [
                    'id' => $rule->id,
                    'factor_code' => $rule->factor_code?->value,
                    'threshold_type' => $rule->threshold_type?->value,
                    'threshold_value' => $this->numeric($rule->threshold_value),
                    'applies_to_increase' => (bool) $rule->applies_to_increase,
                    'applies_to_decrease' => (bool) $rule->applies_to_decrease,
                    'enabled' => (bool) $rule->enabled,
                    'customer_message_template' => $rule->customer_message_template,
                    'internal_message_template' => $rule->internal_message_template,
                ])
                ->all()),
        ];
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }
}
