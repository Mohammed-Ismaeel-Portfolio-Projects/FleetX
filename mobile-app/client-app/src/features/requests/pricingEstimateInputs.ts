import { hasCoordinatePair } from './coordinates';
import type { RequestFormValues } from './requestForm';
import { isCargoTypeValue, isTrailerTypeValue } from './shipmentRequestEnums';
import type { ClientPricingCalculatePayload } from './pricingEstimateTypes';

export type PricingEstimateMissingReason =
  | 'coordinates'
  | 'cargo_type'
  | 'trailer_type'
  | 'cargo_weight';

/**
 * Maps wizard form → estimate API payload when all CalculatePricingRequest
 * required fields are available. Returns null + reasons when incomplete.
 * Does not invent weight or coordinates.
 */
export function resolvePricingEstimatePayload(values: RequestFormValues): {
  payload: ClientPricingCalculatePayload | null;
  missing: PricingEstimateMissingReason[];
} {
  const missing: PricingEstimateMissingReason[] = [];

  if (
    !hasCoordinatePair(values.pickupLatitude, values.pickupLongitude) ||
    !hasCoordinatePair(values.deliveryLatitude, values.deliveryLongitude)
  ) {
    missing.push('coordinates');
  }

  const cargo = values.cargoType.trim();
  if (!cargo || !isCargoTypeValue(cargo)) {
    missing.push('cargo_type');
  }

  const trailer = values.trailerType.trim();
  if (!trailer || !isTrailerTypeValue(trailer)) {
    missing.push('trailer_type');
  }

  const weightRaw = values.cargoWeight.trim();
  const weight = weightRaw === '' ? null : Number(weightRaw);
  if (weight == null || !Number.isFinite(weight) || weight < 0) {
    missing.push('cargo_weight');
  }

  if (missing.length > 0) {
    return { payload: null, missing };
  }

  return {
    payload: {
      pickup_latitude: values.pickupLatitude as number,
      pickup_longitude: values.pickupLongitude as number,
      delivery_latitude: values.deliveryLatitude as number,
      delivery_longitude: values.deliveryLongitude as number,
      cargo_type: cargo,
      trailer_type: trailer,
      cargo_weight: weight as number,
      // Matches submit-time default when ShipmentOrder.priority is null.
      shipment_priority: 'standard',
      border_crossing: false,
      hazmat: Boolean(values.hazardousCargo),
      insurance_required: false,
    },
    missing: [],
  };
}

/** Stable signature of pricing-relevant inputs (for cache / race control). */
export function pricingEstimateSignature(payload: ClientPricingCalculatePayload): string {
  return [
    payload.pickup_latitude,
    payload.pickup_longitude,
    payload.delivery_latitude,
    payload.delivery_longitude,
    payload.cargo_type,
    payload.trailer_type,
    payload.cargo_weight,
    payload.shipment_priority,
    payload.border_crossing ? 1 : 0,
    payload.hazmat ? 1 : 0,
    payload.insurance_required ? 1 : 0,
  ].join('|');
}

export function describeMissingPricingInputs(missing: PricingEstimateMissingReason[]): string {
  if (missing.includes('cargo_weight') && missing.length === 1) {
    return 'Enter cargo weight on the Cargo step to calculate an estimated cost.';
  }
  const labels: string[] = [];
  if (missing.includes('coordinates')) labels.push('pickup and delivery map locations');
  if (missing.includes('cargo_type')) labels.push('cargo type');
  if (missing.includes('trailer_type')) labels.push('trailer type');
  if (missing.includes('cargo_weight')) labels.push('cargo weight');
  return `Estimated cost needs ${labels.join(', ')}.`;
}
