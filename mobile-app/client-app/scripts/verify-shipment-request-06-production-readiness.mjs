/**
 * SHIPMENT-REQUEST-06 — Production readiness regression (static + behavioral mirrors).
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
const stepGate = readClient('src/features/requests/requestStepValidation.ts');
const enums = readClient('src/features/requests/shipmentRequestEnums.ts');
const estimateApi = readClient('src/features/requests/pricingEstimateApi.ts');
const estimateInputs = readClient('src/features/requests/pricingEstimateInputs.ts');
const estimateCard = readClient('src/features/requests/RequestPricingEstimateCard.tsx');
const estimateHook = readClient('src/features/requests/useRequestPricingEstimate.ts');
const api = readClient('src/features/requests/requestsApi.ts');
const wizard = readClient('src/features/requests/requestWizard.ts');

test('02 cargo/trailer enum contract: labels UI-only; payload uses values', () => {
  assert.match(enums, /value: 'general'/);
  assert.match(enums, /label: 'General Freight'/);
  assert.match(enums, /value: 'dry_van'/);
  assert.match(enums, /label: 'Dry Van'/);
  assert.match(requestForm, /cargo_type: nullableString\(values\.cargoType\)/);
  assert.match(requestForm, /trailer_type: nullableString\(values\.trailerType\)/);
  assert.match(form, /CARGO_TYPE_OPTIONS\.map/);
  assert.match(form, /option\.value/);
  assert.equal(form.includes('cargo_type: values.cargoTypeLabel'), false);
  assert.equal(form.includes('setField(\'cargoType\', option.label)'), false);
});

test('03 step gating + draft permissiveness remain', () => {
  assert.match(form, /tryContinue/);
  assert.match(form, /trySelectEditStep/);
  assert.match(form, /validateEditStep/);
  assert.match(form, /validateFormDraft/);
  assert.match(stepGate, /validateRouteStep/);
  assert.match(stepGate, /validateCargoStep/);
  assert.match(stepGate, /before you can proceed/);
  assert.match(requestForm, /Incomplete drafts may omit cargo\/trailer/);
});

test('04 estimate: only calculate endpoint; no fabricated unavailable total', () => {
  assert.match(estimateApi, /\/client\/pricing\/calculate/);
  assert.match(estimateHook, /seqRef|requestSeq|signature/);
  assert.match(estimateCard, /Estimated shipment cost/);
  assert.match(estimateCard, /request-pricing-estimate-incomplete/);
  assert.match(estimateCard, /request-pricing-estimate-error/);
  assert.match(estimateInputs, /cargo_weight/);
  assert.match(estimateInputs, /shipment_priority: 'standard'/);
  assert.match(estimateApi, /distance_km: asNumber\(raw\.distance_km\)/);
  assert.equal(estimateApi.includes('distance_km: asNumber(raw.distance_km) ?? 0'), false);
  assert.equal(form.includes('PricingEngine'), false);
  assert.equal(estimateApi.includes('freight *'), false);
});

test('05 review + single-flight submit/resubmit', () => {
  assert.match(form, /request-review-readiness/);
  assert.match(form, /request-review-edit-route/);
  assert.match(form, /submitInFlight\.current/);
  assert.match(form, /Submitting…/);
  assert.match(form, /submitClientShipmentRequest/);
  assert.match(form, /resubmitClientShipmentRequest/);
  assert.match(form, /splitApiFieldErrors/);
  assert.match(form, /couldn't confirm whether submission completed/);
  assert.match(api, /\/submit/);
  assert.match(api, /\/resubmit/);
});

test('error mapping covers submit contract fields → wizard steps', () => {
  const requiredApiKeys = [
    'cargo_type',
    'trailer_type',
    'cargo_description',
    'pickup_city',
    'pickup_country',
    'delivery_city',
    'delivery_country',
    'pickup_latitude',
    'pickup_longitude',
    'delivery_latitude',
    'delivery_longitude',
    'cargo_weight',
    'package_count',
    'requested_pickup_date',
    'requested_delivery_date',
  ];
  for (const key of requiredApiKeys) {
    assert.match(requestForm, new RegExp(`${key}:`));
  }
  assert.match(wizard, /cargoType: 3/);
  assert.match(wizard, /trailerType: 3/);
  assert.match(wizard, /pickupCity: 2/);
  assert.match(wizard, /requestedPickupDate: 4/);
});

test('no frontend pricing formulas or lifecycle recreation', () => {
  const surface = [form, estimateApi, estimateInputs, estimateCard, estimateHook, requestForm].join('\n');
  assert.equal(/baseRate|pricePerKm|taxRate\s*\*|subtotal\s*\+|PricingEngine/.test(surface), false);
  assert.equal(form.includes("status: 'submitted'"), false);
  assert.equal(form.includes('status_name ='), false);
});
