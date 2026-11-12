import type { CargoType, CustomerType, PricingEstimateRequest, ShipmentPriority, TrailerType } from '../types/pricing.types';

export interface PricingEstimatorFormState {
  pickup_latitude: string;
  pickup_longitude: string;
  delivery_latitude: string;
  delivery_longitude: string;
  cargo_type: CargoType;
  trailer_type: TrailerType;
  cargo_weight: string;
  shipment_priority: ShipmentPriority;
  border_crossing: boolean;
  hazmat: boolean;
  insurance_required: boolean;
  loading_waiting_time: string;
  unloading_waiting_time: string;
  traffic_delay: string;
  customer_delay: string;
  selected_service_ids: number[];
  customer_company_id: string;
  customer_type: '' | CustomerType;
  distance_km: string;
  pricing_policy_id: string;
}

export const defaultEstimatorState: PricingEstimatorFormState = {
  pickup_latitude: '52.2297',
  pickup_longitude: '21.0122',
  delivery_latitude: '50.0647',
  delivery_longitude: '19.9450',
  cargo_type: 'general',
  trailer_type: 'dry_van',
  cargo_weight: '15',
  shipment_priority: 'standard',
  border_crossing: false,
  hazmat: false,
  insurance_required: false,
  loading_waiting_time: '0',
  unloading_waiting_time: '0',
  traffic_delay: '0',
  customer_delay: '0',
  selected_service_ids: [],
  customer_company_id: '',
  customer_type: '',
  distance_km: '',
  pricing_policy_id: '',
};

function toNumber(value: string): number | null {
  if (value.trim() === '') {
    return null;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

export function buildPricingEstimatePayload(form: PricingEstimatorFormState): PricingEstimateRequest {
  const pickupLatitude = toNumber(form.pickup_latitude);
  const pickupLongitude = toNumber(form.pickup_longitude);
  const deliveryLatitude = toNumber(form.delivery_latitude);
  const deliveryLongitude = toNumber(form.delivery_longitude);
  const cargoWeight = toNumber(form.cargo_weight);

  if (
    pickupLatitude === null
    || pickupLongitude === null
    || deliveryLatitude === null
    || deliveryLongitude === null
    || cargoWeight === null
  ) {
    throw new Error('Origin, destination, and cargo weight are required for an estimate.');
  }

  const payload: PricingEstimateRequest = {
    pickup_latitude: pickupLatitude,
    pickup_longitude: pickupLongitude,
    delivery_latitude: deliveryLatitude,
    delivery_longitude: deliveryLongitude,
    cargo_type: form.cargo_type,
    trailer_type: form.trailer_type,
    cargo_weight: cargoWeight,
    shipment_priority: form.shipment_priority,
    border_crossing: form.border_crossing,
    hazmat: form.hazmat,
    insurance_required: form.insurance_required,
    loading_waiting_time: toNumber(form.loading_waiting_time) ?? 0,
    unloading_waiting_time: toNumber(form.unloading_waiting_time) ?? 0,
    traffic_delay: toNumber(form.traffic_delay) ?? 0,
    customer_delay: toNumber(form.customer_delay) ?? 0,
  };

  if (form.selected_service_ids.length > 0) {
    payload.selected_service_ids = form.selected_service_ids;
  }

  if (form.customer_company_id) {
    payload.customer_company_id = form.customer_company_id;
  }

  if (form.customer_type) {
    payload.customer_type = form.customer_type;
  }

  const distanceOverride = toNumber(form.distance_km);
  if (distanceOverride !== null) {
    payload.distance_km = distanceOverride;
  }

  const policyOverride = toNumber(form.pricing_policy_id);
  if (policyOverride !== null) {
    payload.pricing_policy_id = policyOverride;
  }

  if (!payload.customer_company_id && !payload.customer_type) {
    throw new Error('Select a customer company or a customer-type simulation override.');
  }

  return payload;
}
