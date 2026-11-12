import type { PricingPolicyInput } from '../types/pricing.types';

export function buildDefaultPolicyPayload(name = 'New Draft Policy'): PricingPolicyInput {
  return {
    name,
    description: '',
    currency: 'USD',
    effective_from: null,
    effective_until: null,
    priority: 0,
    tax_rate: 8,
    tax_inclusive: false,
    minimum_fare: null,
    base_rates: [
      { cargo_type: 'general', trailer_type: 'dry_van', rate_per_km: 1.45 },
      { cargo_type: 'refrigerated', trailer_type: 'reefer', rate_per_km: 1.95 },
      { cargo_type: 'heavy', trailer_type: 'lowboy', rate_per_km: 2.85 },
      { cargo_type: 'hazardous', trailer_type: 'tanker', rate_per_km: 2.50 },
      { cargo_type: 'fragile', trailer_type: 'dry_van', rate_per_km: 1.55 },
    ],
    distance_tiers: [
      { min_distance_km: 0, max_distance_km: 100, rate_per_km: 1.20 },
      { min_distance_km: 101, max_distance_km: 300, rate_per_km: 0.95 },
      { min_distance_km: 301, max_distance_km: 700, rate_per_km: 0.80 },
      { min_distance_km: 701, max_distance_km: null, rate_per_km: 0.65 },
    ],
    weight_config: {
      threshold_tons: 20,
      charge_per_ton: 50,
    },
    waiting_config: {
      loading_free_minutes: 30,
      unloading_free_minutes: 30,
      rate_per_15min: 15,
      max_charge: 200,
      enabled: true,
    },
    fees: [],
    priority_multipliers: [
      { priority_level: 'standard', multiplier: 1.0 },
      { priority_level: 'express', multiplier: 1.1 },
      { priority_level: 'urgent', multiplier: 1.2 },
      { priority_level: 'emergency', multiplier: 1.3 },
    ],
    discounts: [],
    service_catalog: [],
    variance_rules: [],
  };
}

export function policyToInput(policy: {
  name: string;
  description: string | null;
  currency: string;
  effective_from: string | null;
  effective_until: string | null;
  priority: number;
  tax_rate: number | null;
  tax_inclusive: boolean;
  minimum_fare: number | null;
  base_rates: Array<{ cargo_type: string | null; trailer_type: string | null; rate_per_km: number | null }>;
  distance_tiers: Array<{ min_distance_km: number | null; max_distance_km: number | null; rate_per_km: number | null }>;
  weight_config: { threshold_tons: number | null; charge_per_ton: number | null } | null;
  waiting_config: {
    loading_free_minutes: number;
    unloading_free_minutes: number;
    rate_per_15min: number | null;
    max_charge: number | null;
    enabled: boolean;
  } | null;
  fees: Array<{
    code: string;
    name: string;
    fee_type: string;
    amount: number | null;
    condition_field?: string | null;
    condition_value?: string | null;
    max_amount?: number | null;
    enabled: boolean;
  }>;
  priority_multipliers: Array<{ priority_level: string; multiplier: number | null }>;
  discounts: Array<{
    customer_type: string;
    discount_type: string;
    amount: number | null;
    max_discount?: number | null;
    enabled: boolean;
  }>;
  service_catalog: Array<{
    name: string;
    description: string | null;
    price: number | null;
    enabled: boolean;
  }>;
  variance_rules: Array<{
    factor_code: string | null;
    threshold_type: string | null;
    threshold_value: number | null;
    applies_to_increase: boolean;
    applies_to_decrease: boolean;
    enabled: boolean;
    customer_message_template?: string | null;
    internal_message_template?: string | null;
  }>;
}): PricingPolicyInput {
  return {
    name: policy.name,
    description: policy.description ?? '',
    currency: policy.currency,
    effective_from: policy.effective_from,
    effective_until: policy.effective_until,
    priority: policy.priority,
    tax_rate: policy.tax_rate ?? 0,
    tax_inclusive: policy.tax_inclusive,
    minimum_fare: policy.minimum_fare,
    base_rates: policy.base_rates.map((rate) => ({
      cargo_type: rate.cargo_type ?? 'general',
      trailer_type: rate.trailer_type,
      rate_per_km: rate.rate_per_km ?? 0,
    })),
    distance_tiers: policy.distance_tiers.map((tier) => ({
      min_distance_km: tier.min_distance_km ?? 0,
      max_distance_km: tier.max_distance_km,
      rate_per_km: tier.rate_per_km ?? 0,
    })),
    weight_config: policy.weight_config
      ? {
          threshold_tons: policy.weight_config.threshold_tons ?? 0,
          charge_per_ton: policy.weight_config.charge_per_ton ?? 0,
        }
      : null,
    waiting_config: policy.waiting_config
      ? {
          loading_free_minutes: policy.waiting_config.loading_free_minutes,
          unloading_free_minutes: policy.waiting_config.unloading_free_minutes,
          rate_per_15min: policy.waiting_config.rate_per_15min ?? 0,
          max_charge: policy.waiting_config.max_charge ?? 0,
          enabled: policy.waiting_config.enabled,
        }
      : null,
    fees: policy.fees.map((fee) => ({
      code: fee.code,
      name: fee.name,
      fee_type: fee.fee_type,
      amount: fee.amount ?? 0,
      condition_field: fee.condition_field ?? null,
      condition_value: fee.condition_value ?? null,
      max_amount: fee.max_amount ?? null,
      enabled: fee.enabled,
    })),
    priority_multipliers: policy.priority_multipliers.map((item) => ({
      priority_level: item.priority_level,
      multiplier: item.multiplier ?? 1,
    })),
    discounts: policy.discounts.map((item) => ({
      customer_type: item.customer_type,
      discount_type: item.discount_type,
      amount: item.amount ?? 0,
      max_discount: item.max_discount ?? null,
      enabled: item.enabled,
    })),
    service_catalog: policy.service_catalog.map((item) => ({
      name: item.name,
      description: item.description,
      price: item.price ?? 0,
      enabled: item.enabled,
    })),
    variance_rules: policy.variance_rules
      .filter((rule) => rule.factor_code && rule.threshold_type)
      .map((rule) => ({
        factor_code: rule.factor_code as string,
        threshold_type: rule.threshold_type as string,
        threshold_value: rule.threshold_value ?? 0,
        applies_to_increase: rule.applies_to_increase,
        applies_to_decrease: rule.applies_to_decrease,
        enabled: rule.enabled,
        customer_message_template: rule.customer_message_template ?? null,
        internal_message_template: rule.internal_message_template ?? null,
      })),
  };
}
