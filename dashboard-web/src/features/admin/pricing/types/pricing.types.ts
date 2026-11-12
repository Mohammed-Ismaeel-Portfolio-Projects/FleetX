export type PricingPolicyStatus = 'draft' | 'active' | 'scheduled' | 'expired' | 'archived';

export type PricingPolicyTemporalState =
  | 'draft'
  | 'current'
  | 'future'
  | 'expired'
  | 'archived'
  | 'active_not_selected';

export type CargoType = 'general' | 'refrigerated' | 'heavy' | 'hazardous' | 'fragile';
export type TrailerType = 'dry_van' | 'reefer' | 'flatbed' | 'lowboy' | 'tanker';
export type CustomerType = 'standard' | 'corporate' | 'vip' | 'long_term';
export type ShipmentPriority = 'standard' | 'express' | 'urgent' | 'emergency';

export interface PricingPolicySummary {
  id: number;
  name: string;
  description: string | null;
  status: PricingPolicyStatus;
  priority: number;
  currency: string;
  effective_from: string | null;
  effective_until: string | null;
  tax_rate: number | null;
  tax_inclusive: boolean;
  minimum_fare: number | null;
  temporal_state?: PricingPolicyTemporalState;
  created_at: string | null;
  updated_at: string | null;
}

export interface PricingBaseRate {
  id: number;
  cargo_type: string | null;
  trailer_type: string | null;
  rate_per_km: number | null;
}

export interface PricingDistanceTier {
  id: number;
  min_distance_km: number | null;
  max_distance_km: number | null;
  rate_per_km: number | null;
}

export interface PricingWeightConfig {
  id: number;
  threshold_tons: number | null;
  charge_per_ton: number | null;
}

export interface PricingWaitingConfig {
  id: number;
  loading_free_minutes: number;
  unloading_free_minutes: number;
  rate_per_15min: number | null;
  max_charge: number | null;
  enabled: boolean;
}

export interface PricingFee {
  id: number;
  code: string;
  name: string;
  fee_type: string;
  amount: number | null;
  condition_field: string | null;
  condition_value: string | null;
  max_amount: number | null;
  enabled: boolean;
}

export interface PricingPriorityMultiplier {
  id: number;
  priority_level: string;
  multiplier: number | null;
}

export interface PricingDiscount {
  id: number;
  customer_type: string;
  discount_type: string;
  amount: number | null;
  max_discount: number | null;
  enabled: boolean;
}

export interface PricingServiceCatalogItem {
  id: number;
  name: string;
  description: string | null;
  price: number | null;
  enabled: boolean;
}

export interface PricingVarianceRule {
  id: number;
  factor_code: string | null;
  threshold_type: string | null;
  threshold_value: number | null;
  applies_to_increase: boolean;
  applies_to_decrease: boolean;
  enabled: boolean;
  customer_message_template: string | null;
  internal_message_template: string | null;
}

export interface PricingPolicy extends PricingPolicySummary {
  base_rates: PricingBaseRate[];
  distance_tiers: PricingDistanceTier[];
  weight_config: PricingWeightConfig | null;
  waiting_config: PricingWaitingConfig | null;
  fees: PricingFee[];
  priority_multipliers: PricingPriorityMultiplier[];
  discounts: PricingDiscount[];
  service_catalog: PricingServiceCatalogItem[];
  variance_rules: PricingVarianceRule[];
}

export interface PricingEstimateRequest {
  pickup_latitude: number;
  pickup_longitude: number;
  delivery_latitude: number;
  delivery_longitude: number;
  cargo_type: CargoType;
  trailer_type: TrailerType;
  cargo_weight: number;
  shipment_priority: ShipmentPriority;
  border_crossing?: boolean;
  hazmat?: boolean;
  insurance_required?: boolean;
  loading_waiting_time?: number;
  unloading_waiting_time?: number;
  traffic_delay?: number;
  customer_delay?: number;
  selected_service_ids?: number[];
  customer_company_id?: string;
  customer_type?: CustomerType;
  distance_km?: number;
  pricing_policy_id?: number;
}

export interface PricingEstimateResponse {
  currency: string;
  pricing_policy: {
    id: number;
    name: string;
    source: string;
  };
  customer_type: {
    value: string;
    source: string;
  };
  distance: {
    distance_km: number;
    distance_source: string;
    route_distance_km?: number;
    manual_distance_km?: number;
  };
  charges: {
    freight: number;
    weight_surcharge: number;
    waiting_charge: number;
    services: number;
    border: number;
    insurance: number;
    hazmat: number;
    heavy_reefer: number;
    priority: number;
  };
  subtotal: number;
  discount: {
    amount: number;
    type: string | null;
    rate: number | null;
  };
  after_discount: number;
  minimum_fare_adjustment: number;
  after_minimum_fare: number;
  tax: {
    amount: number;
    rate: number | null;
  };
  total: number;
  service_items: Array<{ name: string; price: number }>;
  warnings: string[];
  applied_rules: string[];
  pricing_rule_resolution: {
    base_rate: number;
    distance_tier_rate: number;
    base_rate_resolution_type: string;
    tax_rate: number | null;
  };
}

export interface PricingApiErrorPayload {
  [field: string]: string[] | string | undefined;
}

export interface PricingPolicyInput {
  name: string;
  description?: string | null;
  currency: string;
  effective_from?: string | null;
  effective_until?: string | null;
  priority: number;
  tax_rate: number;
  tax_inclusive: boolean;
  minimum_fare?: number | null;
  base_rates: Array<{
    cargo_type: string;
    trailer_type?: string | null;
    rate_per_km: number;
  }>;
  distance_tiers: Array<{
    min_distance_km: number;
    max_distance_km?: number | null;
    rate_per_km: number;
  }>;
  weight_config?: {
    threshold_tons: number;
    charge_per_ton: number;
  } | null;
  waiting_config?: {
    loading_free_minutes: number;
    unloading_free_minutes: number;
    rate_per_15min: number;
    max_charge: number;
    enabled?: boolean;
  } | null;
  fees?: Array<{
    code: string;
    name: string;
    fee_type: string;
    amount: number;
    condition_field?: string | null;
    condition_value?: string | null;
    max_amount?: number | null;
    enabled?: boolean;
  }>;
  priority_multipliers?: Array<{
    priority_level: string;
    multiplier: number;
  }>;
  discounts?: Array<{
    customer_type: string;
    discount_type: string;
    amount: number;
    max_discount?: number | null;
    enabled?: boolean;
  }>;
  service_catalog?: Array<{
    name: string;
    description?: string | null;
    price: number;
    enabled?: boolean;
  }>;
  variance_rules?: Array<{
    factor_code: string;
    threshold_type: string;
    threshold_value: number;
    applies_to_increase?: boolean;
    applies_to_decrease?: boolean;
    enabled?: boolean;
    customer_message_template?: string | null;
    internal_message_template?: string | null;
  }>;
}
