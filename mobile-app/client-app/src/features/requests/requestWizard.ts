import type { RequestFormField } from './requestForm';

export type RequestEditStep = 1 | 2 | 3 | 4 | 5;

export const REQUEST_WIZARD_STEPS = [
  { step: 1 as const, key: 'details', label: 'Details' },
  { step: 2 as const, key: 'route', label: 'Route' },
  { step: 3 as const, key: 'cargo', label: 'Cargo' },
  { step: 4 as const, key: 'schedule', label: 'Schedule' },
  { step: 5 as const, key: 'notes', label: 'Notes' },
  { step: 6 as const, key: 'review', label: 'Review' },
] as const;

/** @deprecated Use CARGO_TYPE_OPTIONS from shipmentRequestEnums — kept name for script compatibility. */
export { CARGO_TYPE_OPTIONS as CARGO_TYPE_SUGGESTIONS } from './shipmentRequestEnums';
export { TRAILER_TYPE_OPTIONS } from './shipmentRequestEnums';

const FIELD_STEP: Record<RequestFormField, RequestEditStep> = {
  referenceNumber: 1,
  clientNotes: 1,
  pickupCompany: 2,
  pickupContact: 2,
  pickupPhone: 2,
  pickupAddress: 2,
  pickupCity: 2,
  pickupCountry: 2,
  pickupLatitude: 2,
  pickupLongitude: 2,
  pickupMapLabel: 2,
  pickupDetectedCity: 2,
  pickupDetectedCountry: 2,
  deliveryCompany: 2,
  deliveryContact: 2,
  deliveryPhone: 2,
  deliveryAddress: 2,
  deliveryCity: 2,
  deliveryCountry: 2,
  deliveryLatitude: 2,
  deliveryLongitude: 2,
  deliveryMapLabel: 2,
  deliveryDetectedCity: 2,
  deliveryDetectedCountry: 2,
  cargoType: 3,
  cargoDescription: 3,
  trailerType: 3,
  cargoWeight: 3,
  cargoVolume: 3,
  packageCount: 3,
  requestedPickupDate: 4,
  requestedDeliveryDate: 4,
  hazardousCargo: 5,
  temperatureControlled: 5,
  specialInstructions: 5,
};

export function stepForField(field: RequestFormField): RequestEditStep {
  return FIELD_STEP[field];
}

export function firstInvalidStep(
  errors: Partial<Record<RequestFormField, string>>
): RequestEditStep | null {
  let earliest: RequestEditStep | null = null;
  for (const key of Object.keys(errors) as RequestFormField[]) {
    if (!errors[key]) continue;
    const step = stepForField(key);
    if (earliest == null || step < earliest) earliest = step;
  }
  return earliest;
}

export function firstFieldError(
  errors: Partial<Record<RequestFormField, string>>
): string | null {
  for (const key of Object.keys(errors) as RequestFormField[]) {
    const message = errors[key];
    if (message) return message;
  }
  return null;
}

export function locationHeadline(
  company: string,
  city: string,
  country: string
): string | null {
  const parts = [company, city, country]
    .map((part) => part.trim())
    .filter(Boolean);
  return parts.length > 0 ? parts.join(', ') : null;
}

export function locationAddressLine(address: string): string | null {
  const trimmed = address.trim();
  return trimmed ? trimmed : null;
}

export function contactLine(name: string, phone: string): string | null {
  const parts = [name.trim(), phone.trim()].filter(Boolean);
  return parts.length > 0 ? parts.join(' · ') : null;
}
