import { hasCoordinatePair } from './coordinates';
import { parseRequestDate } from './requestDates';
import { isCargoTypeValue, isTrailerTypeValue } from './shipmentRequestEnums';
import { validateAllEditSteps } from './requestStepValidation';
import type { ClientShipmentRequestDetails, ClientShipmentRequestWritePayload } from './types';

export interface RequestFormValues {
  referenceNumber: string;
  cargoType: string;
  cargoDescription: string;
  trailerType: string;
  cargoWeight: string;
  cargoVolume: string;
  packageCount: string;
  hazardousCargo: boolean;
  temperatureControlled: boolean;
  pickupCompany: string;
  pickupContact: string;
  pickupPhone: string;
  pickupAddress: string;
  pickupCity: string;
  pickupCountry: string;
  pickupLatitude: number | null;
  pickupLongitude: number | null;
  pickupMapLabel: string;
  pickupDetectedCity: string;
  pickupDetectedCountry: string;
  deliveryCompany: string;
  deliveryContact: string;
  deliveryPhone: string;
  deliveryAddress: string;
  deliveryCity: string;
  deliveryCountry: string;
  deliveryLatitude: number | null;
  deliveryLongitude: number | null;
  deliveryMapLabel: string;
  deliveryDetectedCity: string;
  deliveryDetectedCountry: string;
  requestedPickupDate: string;
  requestedDeliveryDate: string;
  specialInstructions: string;
  clientNotes: string;
}

export type RequestFormField = keyof RequestFormValues;

function asText(value: string | null | undefined): string {
  return value == null ? '' : String(value);
}

function asNumberText(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return '';
  return String(value);
}

export function emptyRequestForm(): RequestFormValues {
  return {
    referenceNumber: '',
    cargoType: '',
    cargoDescription: '',
    trailerType: '',
    cargoWeight: '',
    cargoVolume: '',
    packageCount: '',
    hazardousCargo: false,
    temperatureControlled: false,
    pickupCompany: '',
    pickupContact: '',
    pickupPhone: '',
    pickupAddress: '',
    pickupCity: '',
    pickupCountry: '',
    pickupLatitude: null,
    pickupLongitude: null,
    pickupMapLabel: '',
    pickupDetectedCity: '',
    pickupDetectedCountry: '',
    deliveryCompany: '',
    deliveryContact: '',
    deliveryPhone: '',
    deliveryAddress: '',
    deliveryCity: '',
    deliveryCountry: '',
    deliveryLatitude: null,
    deliveryLongitude: null,
    deliveryMapLabel: '',
    deliveryDetectedCity: '',
    deliveryDetectedCountry: '',
    requestedPickupDate: '',
    requestedDeliveryDate: '',
    specialInstructions: '',
    clientNotes: '',
  };
}

export function formFromDetails(details: ClientShipmentRequestDetails): RequestFormValues {
  return {
    referenceNumber: asText(details.referenceNumber),
    cargoType: asText(details.cargoType),
    cargoDescription: asText(details.cargoDescription),
    trailerType: asText(details.trailerType),
    cargoWeight: asNumberText(details.cargoWeight),
    cargoVolume: asNumberText(details.cargoVolume),
    packageCount: details.packageCount != null ? String(details.packageCount) : '',
    hazardousCargo: Boolean(details.hazardousCargo),
    temperatureControlled: Boolean(details.temperatureControlled),
    pickupCompany: asText(details.pickupCompany),
    pickupContact: asText(details.pickupContact),
    pickupPhone: asText(details.pickupPhone),
    pickupAddress: asText(details.pickupAddress),
    pickupCity: asText(details.pickupCity),
    pickupCountry: asText(details.pickupCountry),
    pickupLatitude: details.pickupLatitude,
    pickupLongitude: details.pickupLongitude,
    pickupMapLabel: hasCoordinatePair(details.pickupLatitude, details.pickupLongitude)
      ? 'Location selected'
      : '',
    pickupDetectedCity: '',
    pickupDetectedCountry: '',
    deliveryCompany: asText(details.deliveryCompany),
    deliveryContact: asText(details.deliveryContact),
    deliveryPhone: asText(details.deliveryPhone),
    deliveryAddress: asText(details.deliveryAddress),
    deliveryCity: asText(details.deliveryCity),
    deliveryCountry: asText(details.deliveryCountry),
    deliveryLatitude: details.deliveryLatitude,
    deliveryLongitude: details.deliveryLongitude,
    deliveryMapLabel: hasCoordinatePair(details.deliveryLatitude, details.deliveryLongitude)
      ? 'Location selected'
      : '',
    deliveryDetectedCity: '',
    deliveryDetectedCountry: '',
    requestedPickupDate: asText(details.requestedPickupDate).slice(0, 10),
    requestedDeliveryDate: asText(details.requestedDeliveryDate).slice(0, 10),
    specialInstructions: asText(details.specialInstructions),
    clientNotes: asText(details.clientNotes),
  };
}

function nullableString(value: string): string | null {
  const trimmed = value.trim();
  return trimmed ? trimmed : null;
}

function nullableNumber(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const n = Number(trimmed);
  return Number.isFinite(n) ? n : null;
}

function nullableInt(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const n = Number.parseInt(trimmed, 10);
  return Number.isFinite(n) ? n : null;
}

/** Maps UI form → API write payload (allowed fields only). Enum values only — never display labels. */
export function formToWritePayload(values: RequestFormValues): ClientShipmentRequestWritePayload {
  return {
    reference_number: nullableString(values.referenceNumber),
    cargo_type: nullableString(values.cargoType),
    cargo_description: nullableString(values.cargoDescription),
    trailer_type: nullableString(values.trailerType),
    cargo_weight: nullableNumber(values.cargoWeight),
    cargo_volume: nullableNumber(values.cargoVolume),
    package_count: nullableInt(values.packageCount),
    hazardous_cargo: values.hazardousCargo,
    temperature_controlled: values.temperatureControlled,
    pickup_company: nullableString(values.pickupCompany),
    pickup_contact: nullableString(values.pickupContact),
    pickup_phone: nullableString(values.pickupPhone),
    pickup_address: nullableString(values.pickupAddress),
    pickup_city: nullableString(values.pickupCity),
    pickup_country: nullableString(values.pickupCountry),
    pickup_latitude: values.pickupLatitude,
    pickup_longitude: values.pickupLongitude,
    delivery_company: nullableString(values.deliveryCompany),
    delivery_contact: nullableString(values.deliveryContact),
    delivery_phone: nullableString(values.deliveryPhone),
    delivery_address: nullableString(values.deliveryAddress),
    delivery_city: nullableString(values.deliveryCity),
    delivery_country: nullableString(values.deliveryCountry),
    delivery_latitude: values.deliveryLatitude,
    delivery_longitude: values.deliveryLongitude,
    requested_pickup_date: nullableString(values.requestedPickupDate),
    requested_delivery_date: nullableString(values.requestedDeliveryDate),
    special_instructions: nullableString(values.specialInstructions),
    client_notes: nullableString(values.clientNotes),
  };
}

const API_TO_FORM_FIELD: Record<string, RequestFormField> = {
  reference_number: 'referenceNumber',
  cargo_type: 'cargoType',
  cargo_description: 'cargoDescription',
  trailer_type: 'trailerType',
  cargo_weight: 'cargoWeight',
  cargo_volume: 'cargoVolume',
  package_count: 'packageCount',
  hazardous_cargo: 'hazardousCargo',
  temperature_controlled: 'temperatureControlled',
  pickup_company: 'pickupCompany',
  pickup_contact: 'pickupContact',
  pickup_phone: 'pickupPhone',
  pickup_address: 'pickupAddress',
  pickup_city: 'pickupCity',
  pickup_country: 'pickupCountry',
  pickup_latitude: 'pickupLatitude',
  pickup_longitude: 'pickupLongitude',
  delivery_company: 'deliveryCompany',
  delivery_contact: 'deliveryContact',
  delivery_phone: 'deliveryPhone',
  delivery_address: 'deliveryAddress',
  delivery_city: 'deliveryCity',
  delivery_country: 'deliveryCountry',
  delivery_latitude: 'deliveryLatitude',
  delivery_longitude: 'deliveryLongitude',
  requested_pickup_date: 'requestedPickupDate',
  requested_delivery_date: 'requestedDeliveryDate',
  special_instructions: 'specialInstructions',
  client_notes: 'clientNotes',
};

export function mapApiFieldErrors(
  errors: Record<string, string> | undefined
): Partial<Record<RequestFormField, string>> {
  if (!errors) return {};
  const out: Partial<Record<RequestFormField, string>> = {};
  for (const [key, message] of Object.entries(errors)) {
    const field = API_TO_FORM_FIELD[key];
    if (field) out[field] = message;
  }
  return out;
}

/** Split API errors into mapped form fields vs leftover messages (SHIPMENT-REQUEST-05). */
export function splitApiFieldErrors(errors: Record<string, string> | undefined): {
  mapped: Partial<Record<RequestFormField, string>>;
  unmappedMessages: string[];
} {
  if (!errors) return { mapped: {}, unmappedMessages: [] };
  const mapped: Partial<Record<RequestFormField, string>> = {};
  const unmappedMessages: string[] = [];
  for (const [key, message] of Object.entries(errors)) {
    const field = API_TO_FORM_FIELD[key];
    if (field) mapped[field] = message;
    else if (message) unmappedMessages.push(message);
  }
  return { mapped, unmappedMessages };
}

/**
 * Client-side check aligned with ShipmentOrderSubmissionValidator readiness rules.
 * Server remains authoritative on submit. Composes SHIPMENT-REQUEST-03 step validators.
 */
export function validateFormForSubmit(values: RequestFormValues): Partial<Record<RequestFormField, string>> {
  return validateAllEditSteps(values).errors;
}

export function validateFormDraft(values: RequestFormValues): Partial<Record<RequestFormField, string>> {
  const errors: Partial<Record<RequestFormField, string>> = {};
  const pickup = values.requestedPickupDate.trim();
  const delivery = values.requestedDeliveryDate.trim();
  if (pickup && !parseRequestDate(pickup)) errors.requestedPickupDate = 'Use date format YYYY-MM-DD.';
  if (delivery && !parseRequestDate(delivery)) {
    errors.requestedDeliveryDate = 'Use date format YYYY-MM-DD.';
  }
  if (pickup && delivery && parseRequestDate(pickup) && parseRequestDate(delivery) && delivery < pickup) {
    errors.requestedDeliveryDate = 'Requested delivery must be on or after requested pickup.';
  }
  const packages = values.packageCount.trim();
  if (packages) {
    const n = Number.parseInt(packages, 10);
    if (!Number.isFinite(n) || n < 1) errors.packageCount = 'Package count must be at least 1.';
  }
  // Incomplete drafts may omit cargo/trailer; if set, must already be enum values.
  const cargo = values.cargoType.trim();
  if (cargo && !isCargoTypeValue(cargo)) {
    errors.cargoType = 'Select a supported cargo type.';
  }
  const trailer = values.trailerType.trim();
  if (trailer && !isTrailerTypeValue(trailer)) {
    errors.trailerType = 'Select a supported trailer type.';
  }
  return errors;
}
