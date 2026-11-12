/**
 * SHIPMENT-REQUEST-04 — Client pricing estimate on Review (static verification).
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
const api = readClient('src/features/requests/pricingEstimateApi.ts');
const inputs = readClient('src/features/requests/pricingEstimateInputs.ts');
const format = readClient('src/features/requests/pricingEstimateFormat.ts');
const hook = readClient('src/features/requests/useRequestPricingEstimate.ts');
const card = readClient('src/features/requests/RequestPricingEstimateCard.tsx');
const enums = readClient('src/features/requests/shipmentRequestEnums.ts');
const stepGate = readClient('src/features/requests/requestStepValidation.ts');

test('estimate uses existing client pricing calculate endpoint only', () => {
  assert.match(api, /\/client\/pricing\/calculate/);
  assert.match(api, /export async function calculateClientPricingEstimate/);
  assert.equal(api.includes('PricingEngine'), false);
  assert.equal(form.includes('PricingEngine'), false);
  assert.equal(format.includes('freightCharge *'), false);
  assert.equal(format.includes('finalTotal'), false);
});

test('payload builder uses enum cargo/trailer and required estimate fields', () => {
  assert.match(inputs, /cargo_type: cargo/);
  assert.match(inputs, /trailer_type: trailer/);
  assert.match(inputs, /cargo_weight/);
  assert.match(inputs, /shipment_priority: 'standard'/);
  assert.match(inputs, /isCargoTypeValue/);
  assert.match(inputs, /isTrailerTypeValue/);
  assert.match(inputs, /hasCoordinatePair/);
  assert.match(inputs, /hazmat: Boolean\(values\.hazardousCargo\)/);
  assert.match(enums, /value: 'general'/);
});

test('Review hosts estimate card with disclaimer and states', () => {
  assert.match(form, /RequestPricingEstimateCard/);
  assert.match(form, /useRequestPricingEstimate\(values, mode === 'review'\)/);
  assert.match(card, /Estimated shipment cost/);
  assert.match(card, /This is an estimated cost/);
  assert.match(card, /final price is calculated when the shipment request is[\s\S]*submitted/);
  assert.match(card, /request-pricing-estimate-loading/);
  assert.match(card, /request-pricing-estimate-error/);
  assert.match(card, /request-pricing-estimate-retry/);
  assert.match(card, /request-pricing-estimate-success/);
  assert.match(card, /request-pricing-estimate-total/);
  assert.match(card, /request-pricing-estimate-incomplete/);
});

test('race safety and no zero fabrication', () => {
  assert.match(hook, /seqRef/);
  assert.match(hook, /inFlightSigRef/);
  assert.match(hook, /successSigRef/);
  assert.match(hook, /if \(seq !== seqRef\.current\) return/);
  assert.match(card, /Unable to calculate the estimated cost/);
  assert.equal(card.includes('"$0"'), false);
  assert.equal(card.includes("'$0'"), false);
  assert.equal(/totalDisplay[\s\S]{0,80}\?\? 0/.test(card), false);
});

test('submit hint keeps estimate informational; gating from 03 intact', () => {
  assert.match(
    form,
    /displayed cost is an estimate[\s\S]*final price is calculated when the request is submitted|estimate above is informational only/
  );
  assert.match(form, /tryContinue/);
  assert.match(form, /validateEditStep/);
  assert.match(stepGate, /validateCargoStep/);
  assert.match(form, /submitClientShipmentRequest/);
  assert.equal(form.includes('persistEstimate'), false);
});

/** Behavioral: weight required for estimate payload */
function resolveMissing(values) {
  const missing = [];
  if (values.pickupLatitude == null || values.deliveryLatitude == null) missing.push('coordinates');
  if (!values.cargoType) missing.push('cargo_type');
  if (!values.trailerType) missing.push('trailer_type');
  const w = values.cargoWeight === '' || values.cargoWeight == null ? null : Number(values.cargoWeight);
  if (w == null || !Number.isFinite(w) || w < 0) missing.push('cargo_weight');
  return missing;
}

test('behavioral: missing weight blocks estimate payload; complete inputs empty missing', () => {
  assert.ok(
    resolveMissing({
      pickupLatitude: 1,
      deliveryLatitude: 2,
      cargoType: 'general',
      trailerType: 'dry_van',
      cargoWeight: '',
    }).includes('cargo_weight')
  );
  assert.deepEqual(
    resolveMissing({
      pickupLatitude: 1,
      deliveryLatitude: 2,
      cargoType: 'general',
      trailerType: 'dry_van',
      cargoWeight: '0',
    }),
    []
  );
});

test('format helpers are presentation-only', () => {
  assert.match(format, /formatEstimateMoney/);
  assert.match(format, /Intl\.NumberFormat/);
  assert.match(format, /estimateChargeRows/);
  assert.equal(format.includes('PricingEngine'), false);
  assert.equal(/baseRate|distanceTier/.test(format), false);
});
