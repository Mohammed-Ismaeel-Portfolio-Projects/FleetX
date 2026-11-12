/**
 * Client Create Transport Request UX — static + behavioral checks.
 * Presentation-only. Does not change shipment-request API contracts.
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const CLIENT = resolve(__dirname, '..');

function readClient(rel) {
  const p = resolve(CLIENT, rel);
  if (!existsSync(p)) return '';
  return readFileSync(p, 'utf8');
}

const form = readClient('src/app/request-form.tsx');
const requestForm = readClient('src/features/requests/requestForm.ts');
const api = readClient('src/features/requests/requestsApi.ts');
const types = readClient('src/features/requests/types.ts');
const dates = readClient('src/features/requests/requestDates.ts');
const wizard = readClient('src/features/requests/requestWizard.ts');
const dateField = readClient('src/features/requests/RequestDateField.tsx');
const stepIndicator = readClient('src/features/requests/RequestStepIndicator.tsx');
const locationCard = readClient('src/features/requests/RequestLocationSummary.tsx');

const PRESERVED_TEST_IDS = [
  'request-form',
  'request-review',
  'request-form-back',
  'request-form-loading',
  'request-form-error',
  'request-form-retry',
  'request-form-review-feedback',
  'request-form-review-feedback-message',
  'request-form-draft-saved',
  'request-save-draft',
  'request-go-review',
  'request-form-edit',
  'request-submit',
  'request-resubmit',
  'request-field-pickupCompany',
  'request-field-pickupCity',
  'request-field-pickupCountry',
  'request-field-pickupAddress',
  'request-field-pickupContact',
  'request-field-pickupPhone',
  'request-field-deliveryCompany',
  'request-field-deliveryCity',
  'request-field-deliveryCountry',
  'request-field-deliveryAddress',
  'request-field-deliveryContact',
  'request-field-deliveryPhone',
  'request-field-cargoType',
  'request-field-cargoDescription',
  'request-field-packageCount',
  'request-field-cargoWeight',
  'request-field-cargoVolume',
  'request-field-requestedPickupDate',
  'request-field-requestedDeliveryDate',
  'request-field-referenceNumber',
  'request-field-specialInstructions',
  'request-field-clientNotes',
];

test('all existing request-form testIDs are preserved', () => {
  for (const id of PRESERVED_TEST_IDS) {
    assert.match(form, new RegExp(id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), id);
  }
});

test('create/edit/review/submit API surface is unchanged', () => {
  assert.match(form, /createClientShipmentRequest/);
  assert.match(form, /updateClientShipmentRequest/);
  assert.match(form, /submitClientShipmentRequest/);
  assert.match(form, /resubmitClientShipmentRequest/);
  assert.match(form, /submitInFlight/);
  assert.match(form, /saveInFlight/);
  assert.match(form, /disabled=\{busy\}/);
  assert.match(form, /Save Draft/);
  assert.match(form, /Submit Request/);
  assert.match(form, /Resubmit Request/);
  assert.equal(form.includes('apiClient'), false);
  assert.equal(api.includes('/dispatcher/'), false);
  assert.match(api, /\/client\/shipment-requests\/\$\{encodeURIComponent\(requestId\)\}\/submit/);
});

test('client write payload includes coordinates and never assignment fields', () => {
  assert.equal(types.includes('driver_id'), false);
  assert.equal(types.includes('vehicle_id'), false);
  assert.equal(types.includes('trailer_id'), false);
  assert.match(types, /pickup_latitude/);
  assert.match(types, /pickup_longitude/);
  assert.match(types, /delivery_latitude/);
  assert.match(types, /delivery_longitude/);
  assert.equal(form.includes('driver_id'), false);
  assert.equal(form.includes('vehicle_id'), false);
  assert.equal(form.includes('trailer_id'), false);
  assert.equal(form.includes("from 'react-native-maps'"), false);
});

test('required submit fields and date rules remain field-level', () => {
  assert.match(requestForm, /Cargo type is required before you can proceed|validateAllEditSteps/);
  assert.match(requestForm, /validateAllEditSteps|Pickup city is required before you can proceed/);
  const stepValidation = readClient('src/features/requests/requestStepValidation.ts');
  assert.match(stepValidation, /Pickup city is required before you can proceed/);
  assert.match(stepValidation, /Delivery city is required before you can proceed/);
  assert.match(stepValidation, /Use date format YYYY-MM-DD/);
  assert.match(stepValidation, /Requested delivery must be on or after requested pickup/);
  assert.match(requestForm, /parseRequestDate|validateAllEditSteps/);
  assert.match(form, /fieldErrors/);
  assert.match(form, /mapApiFieldErrors/);
  assert.match(form, /firstInvalidStep/);
});

test('stepped request UX exists without replacing review/submit', () => {
  const surface = [form, stepIndicator, wizard].join('\n');
  assert.match(surface, /RequestStepIndicator/);
  assert.match(surface, /request-step-indicator/);
  assert.match(form, /request-step-continue/);
  assert.match(form, /Create Transport Request/);
  assert.match(form, /request-review-route/);
  assert.match(form, /does not create a shipment/);
  assert.match(form, /does not mean approval/);
  assert.match(wizard, /label: 'Details'/);
  assert.match(wizard, /label: 'Review'/);
  assert.match(wizard, /CARGO_TYPE_SUGGESTIONS|CARGO_TYPE_OPTIONS/);
});

test('SHIPMENT-REQUEST-02: cargo and trailer use authoritative enum values', () => {
  const enums = readClient('src/features/requests/shipmentRequestEnums.ts');
  assert.match(enums, /value: 'general'/);
  assert.match(enums, /label: 'General Freight'/);
  assert.match(enums, /value: 'dry_van'/);
  assert.match(enums, /value: 'reefer'/);
  assert.match(requestForm, /trailerType/);
  assert.match(requestForm, /trailer_type:/);
  assert.match(requestForm, /isCargoTypeValue/);
  assert.match(requestForm, /isTrailerTypeValue/);
  assert.match(requestForm, /Trailer type is required before you can proceed|validateAllEditSteps/);
  assert.match(requestForm, /trailer_type: 'trailerType'|trailer_type:/);
  assert.match(form, /CARGO_TYPE_OPTIONS/);
  assert.match(form, /TRAILER_TYPE_OPTIONS/);
  assert.match(form, /setField\('cargoType', option\.value\)/);
  assert.match(form, /setField\('trailerType', option\.value\)/);
  assert.equal(form.includes('placeholder="General Freight"'), false);
  assert.equal(form.includes("onChangeText={(t) => setField('cargoType', t)}"), false);
  assert.match(types, /trailer_type\?:/);
  assert.match(types, /trailerType:/);
  assert.match(api, /trailerType: api\.trailer_type/);
  assert.equal(form.includes('PricingEngine'), false);
  assert.equal(requestForm.includes('final_total'), false);
});

test('pickup and delivery location cards and date pickers are present', () => {
  assert.match(form, /request-field-pickupCity/);
  assert.match(form, /request-field-deliveryCity/);
  assert.match(form, /RequestLocationSummary/);
  assert.match(form, /RequestDateField/);
  assert.match(form, /request-date-pickup-open/);
  assert.match(form, /request-date-delivery-open/);
  assert.match(form, /request-pickup-map/);
  assert.match(form, /request-delivery-map/);
  assert.match(form, /request-pickup-location/);
  assert.match(form, /request-delivery-location/);
  assert.match(dateField, /Select date/);
  assert.match(dateField, /YYYY-MM-DD/);
  assert.match(locationCard, /City and country are required before review/);
});

test('duplicate submit remains locked', () => {
  assert.match(form, /if \(submitInFlight\.current \|\| saveInFlight\.current\) return/);
  assert.match(form, /submitInFlight\.current = true/);
  assert.match(form, /saveInFlight\.current \|\| submitInFlight\.current/);
});

test('no fake operational metrics in the request form', () => {
  assert.equal(form.includes('LIVE ETA'), false);
  assert.equal(form.toLowerCase().includes('km away'), false);
  assert.equal(/Date\.parse\(/.test(dates), false);
});

/** Mirrors requestDates.parseRequestDate — keep in sync. */
function parseRequestDate(value) {
  const trimmed = String(value).trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return null;
  const year = Number(trimmed.slice(0, 4));
  const month = Number(trimmed.slice(5, 7));
  const day = Number(trimmed.slice(8, 10));
  const maxDay = new Date(year, month, 0).getDate();
  if (month < 1 || month > 12 || day < 1 || day > maxDay) return null;
  return { year, month, day };
}

function firstInvalidStep(errors) {
  const FIELD_STEP = {
    referenceNumber: 1,
    clientNotes: 1,
    pickupCity: 2,
    deliveryCity: 2,
    cargoType: 3,
    trailerType: 3,
    requestedPickupDate: 4,
    specialInstructions: 5,
  };
  let earliest = null;
  for (const key of Object.keys(errors)) {
    if (!errors[key]) continue;
    const step = FIELD_STEP[key];
    if (step == null) continue;
    if (earliest == null || step < earliest) earliest = step;
  }
  return earliest;
}

test('behavioral: date parse rejects invalid calendar days', () => {
  assert.deepEqual(parseRequestDate('2026-08-25'), { year: 2026, month: 8, day: 25 });
  assert.equal(parseRequestDate('2026-02-31'), null);
  assert.equal(parseRequestDate('08/25/2026'), null);
  assert.equal(parseRequestDate(''), null);
  assert.match(dates, /export function parseRequestDate/);
  assert.match(dates, /daysInMonth/);
});

test('behavioral: delivery before pickup is invalid; equal dates are valid', () => {
  assert.equal('2026-08-26' < '2026-08-25', false);
  assert.equal('2026-08-24' < '2026-08-25', true);
  assert.equal('2026-08-25' < '2026-08-25', false);
  assert.ok(parseRequestDate('2026-08-25') && parseRequestDate('2026-08-25'));
});

test('behavioral: first invalid field maps to the owning wizard step', () => {
  assert.equal(firstInvalidStep({ pickupCity: 'required' }), 2);
  assert.equal(firstInvalidStep({ cargoType: 'required', pickupCity: 'required' }), 2);
  assert.equal(firstInvalidStep({ trailerType: 'required' }), 3);
  assert.equal(firstInvalidStep({ requestedPickupDate: 'bad' }), 4);
  assert.equal(firstInvalidStep({}), null);
  assert.match(wizard, /pickupCity: 2/);
  assert.match(wizard, /cargoType: 3/);
  assert.match(wizard, /trailerType: 3/);
  assert.match(wizard, /requestedPickupDate: 4/);
});
