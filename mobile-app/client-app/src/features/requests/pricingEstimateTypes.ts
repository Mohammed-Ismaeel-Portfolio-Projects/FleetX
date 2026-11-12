/** Client pricing estimate types — mirror ClientPricingEstimateResource. */

export interface ClientPricingEstimateCharges {
  freight: number;
  weight_surcharge: number;
  waiting_charge: number;
  services: number;
  border: number;
  insurance: number;
  hazmat: number;
  heavy_reefer: number;
  priority: number;
}

export interface ClientPricingEstimateDiscount {
  amount: number;
  type: string | null;
  rate: number | null;
}

export interface ClientPricingEstimateTax {
  amount: number;
  rate: number | null;
}

export interface ClientPricingEstimateServiceItem {
  name?: string;
  price?: number;
  [key: string]: unknown;
}

/** Response `data` from POST /api/v1/client/pricing/calculate */
export interface ClientPricingEstimate {
  currency: string;
  /** Null when backend omits distance — never fabricate 0 for missing distance. */
  distance_km: number | null;
  charges: ClientPricingEstimateCharges;
  subtotal: number;
  discount: ClientPricingEstimateDiscount;
  after_discount: number;
  minimum_fare_adjustment: number;
  after_minimum_fare: number;
  tax: ClientPricingEstimateTax;
  total: number;
  service_items: ClientPricingEstimateServiceItem[];
  warnings: string[];
}

/** Body for CalculatePricingRequest — authoritative enum values only. */
export interface ClientPricingCalculatePayload {
  pickup_latitude: number;
  pickup_longitude: number;
  delivery_latitude: number;
  delivery_longitude: number;
  cargo_type: string;
  trailer_type: string;
  cargo_weight: number;
  shipment_priority: 'standard' | 'express' | 'urgent' | 'emergency';
  border_crossing?: boolean;
  hazmat?: boolean;
  insurance_required?: boolean;
}
