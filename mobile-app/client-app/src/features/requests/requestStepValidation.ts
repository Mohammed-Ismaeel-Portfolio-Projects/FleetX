import { hasCoordinatePair, isValidLatitude, isValidLongitude } from './coordinates';
import { parseRequestDate } from './requestDates';
import type { RequestEditStep } from './requestWizard';
import { firstInvalidStep, stepForField } from './requestWizard';
import type { RequestFormField, RequestFormValues } from './requestForm';
import { isCargoTypeValue, isTrailerTypeValue } from './shipmentRequestEnums';

/**
 * Per-step local validation for SHIPMENT-REQUEST-03.
 * Rules mirror existing Client submit readiness (SHIPMENT-REQUEST-02) —
 * no invented required fields (company/address/contact remain optional).
 */

export interface StepValidationResult {
  valid: boolean;
  errors: Partial<Record<RequestFormField, string>>;
  firstInvalidField: RequestFormField | null;
}

function emptyResult(): StepValidationResult {
  return { valid: true, errors: {}, firstInvalidField: null };
}

function resultFrom(errors: Partial<Record<RequestFormField, string>>): StepValidationResult {
  const keys = Object.keys(errors) as RequestFormField[];
  const firstInvalidField = keys.find((k) => Boolean(errors[k])) ?? null;
  return {
    valid: firstInvalidField == null,
    errors,
    firstInvalidField,
  };
}

function requireCoordinatePair(
  errors: Partial<Record<RequestFormField, string>>,
  latitude: number | null,
  longitude: number | null,
  latField: 'pickupLatitude' | 'deliveryLatitude',
  lngField: 'pickupLongitude' | 'deliveryLongitude',
  missingMessage: string
): void {
  const latMissing = latitude == null;
  const lngMissing = longitude == null;

  if (latMissing && lngMissing) {
    errors[latField] = missingMessage;
    return;
  }

  if (latMissing && !lngMissing) {
    errors[latField] = 'Latitude is required with longitude.';
  } else if (!latMissing && !isValidLatitude(latitude)) {
    errors[latField] = 'Enter a valid latitude between -90 and 90.';
  }

  if (lngMissing && !latMissing) {
    errors[lngField] = 'Longitude is required with latitude.';
  } else if (!lngMissing && !isValidLongitude(longitude)) {
    errors[lngField] = 'Enter a valid longitude between -180 and 180.';
  }

  if (!latMissing && !lngMissing && !hasCoordinatePair(latitude, longitude)) {
    if (!errors[latField]) errors[latField] = missingMessage;
  }
}

/** Step 1 — Details: all optional. */
export function validateDetailsStep(_values: RequestFormValues): StepValidationResult {
  return emptyResult();
}

/** Step 2 — Route: cities, countries, coordinate pairs (submit contract). */
export function validateRouteStep(values: RequestFormValues): StepValidationResult {
  const errors: Partial<Record<RequestFormField, string>> = {};
  if (!values.pickupCity.trim()) {
    errors.pickupCity = 'Pickup city is required before you can proceed.';
  }
  if (!values.pickupCountry.trim()) {
    errors.pickupCountry = 'Pickup country is required before you can proceed.';
  }
  requireCoordinatePair(
    errors,
    values.pickupLatitude,
    values.pickupLongitude,
    'pickupLatitude',
    'pickupLongitude',
    'Select pickup on the map before you can proceed.'
  );
  if (!values.deliveryCity.trim()) {
    errors.deliveryCity = 'Delivery city is required before you can proceed.';
  }
  if (!values.deliveryCountry.trim()) {
    errors.deliveryCountry = 'Delivery country is required before you can proceed.';
  }
  requireCoordinatePair(
    errors,
    values.deliveryLatitude,
    values.deliveryLongitude,
    'deliveryLatitude',
    'deliveryLongitude',
    'Select delivery on the map before you can proceed.'
  );
  return resultFrom(errors);
}

/** Step 3 — Cargo: enum cargo/trailer + description; soft numeric rules. */
export function validateCargoStep(values: RequestFormValues): StepValidationResult {
  const errors: Partial<Record<RequestFormField, string>> = {};
  const cargo = values.cargoType.trim();
  if (!cargo) {
    errors.cargoType = 'Cargo type is required before you can proceed.';
  } else if (!isCargoTypeValue(cargo)) {
    errors.cargoType = 'Select a supported cargo type before you can proceed.';
  }
  if (!values.cargoDescription.trim()) {
    errors.cargoDescription = 'Cargo description is required before you can proceed.';
  }
  const trailer = values.trailerType.trim();
  if (!trailer) {
    errors.trailerType = 'Trailer type is required before you can proceed.';
  } else if (!isTrailerTypeValue(trailer)) {
    errors.trailerType = 'Select a supported trailer type before you can proceed.';
  }

  const weight = values.cargoWeight.trim();
  if (weight) {
    const n = Number(weight);
    if (!Number.isFinite(n)) {
      errors.cargoWeight = 'Enter a valid weight.';
    } else if (n < 0) {
      errors.cargoWeight = 'Weight cannot be negative.';
    }
  }
  const volume = values.cargoVolume.trim();
  if (volume) {
    const n = Number(volume);
    if (!Number.isFinite(n)) {
      errors.cargoVolume = 'Enter a valid volume.';
    } else if (n < 0) {
      errors.cargoVolume = 'Volume cannot be negative.';
    }
  }
  const packages = values.packageCount.trim();
  if (packages) {
    const n = Number.parseInt(packages, 10);
    if (!Number.isFinite(n) || n < 1) {
      errors.packageCount = 'Package count must be at least 1.';
    }
  }
  return resultFrom(errors);
}

/** Step 4 — Schedule: optional dates; format + order when provided. */
export function validateScheduleStep(values: RequestFormValues): StepValidationResult {
  const errors: Partial<Record<RequestFormField, string>> = {};
  const pickup = values.requestedPickupDate.trim();
  const delivery = values.requestedDeliveryDate.trim();
  if (pickup && !parseRequestDate(pickup)) {
    errors.requestedPickupDate = 'Use date format YYYY-MM-DD.';
  }
  if (delivery && !parseRequestDate(delivery)) {
    errors.requestedDeliveryDate = 'Use date format YYYY-MM-DD.';
  }
  if (pickup && delivery && parseRequestDate(pickup) && parseRequestDate(delivery) && delivery < pickup) {
    errors.requestedDeliveryDate = 'Requested delivery must be on or after requested pickup.';
  }
  return resultFrom(errors);
}

/** Step 5 — Notes: optional flags/text. */
export function validateNotesStep(_values: RequestFormValues): StepValidationResult {
  return emptyResult();
}

export function validateEditStep(
  step: RequestEditStep,
  values: RequestFormValues
): StepValidationResult {
  switch (step) {
    case 1:
      return validateDetailsStep(values);
    case 2:
      return validateRouteStep(values);
    case 3:
      return validateCargoStep(values);
    case 4:
      return validateScheduleStep(values);
    case 5:
      return validateNotesStep(values);
    default:
      return emptyResult();
  }
}

/**
 * Validate every edit step from `fromStep` through `throughStep` inclusive.
 * Used when jumping forward or opening Review.
 */
export function validateEditStepsThrough(
  throughStep: RequestEditStep,
  values: RequestFormValues,
  fromStep: RequestEditStep = 1
): StepValidationResult {
  const merged: Partial<Record<RequestFormField, string>> = {};
  for (let s = fromStep; s <= throughStep; s += 1) {
    const stepResult = validateEditStep(s as RequestEditStep, values);
    Object.assign(merged, stepResult.errors);
  }
  const firstStep = firstInvalidStep(merged);
  let firstInvalidField: RequestFormField | null = null;
  if (firstStep != null) {
    for (const key of Object.keys(merged) as RequestFormField[]) {
      if (merged[key] && stepForField(key) === firstStep) {
        firstInvalidField = key;
        break;
      }
    }
  }
  return {
    valid: Object.keys(merged).length === 0,
    errors: merged,
    firstInvalidField,
  };
}

/** Full wizard readiness before Review/Submit — same contract as validateFormForSubmit. */
export function validateAllEditSteps(values: RequestFormValues): StepValidationResult {
  return validateEditStepsThrough(5, values, 1);
}
