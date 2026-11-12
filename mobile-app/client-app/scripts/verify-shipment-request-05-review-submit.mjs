/**
 * SHIPMENT-REQUEST-05 — Review + final submission polish (static verification).
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
const details = readClient('src/app/request-details/[id].tsx');
const stepGate = readClient('src/features/requests/requestStepValidation.ts');
const estimateCard = readClient('src/features/requests/RequestPricingEstimateCard.tsx');

test('Review shows readiness and section edit affordances', () => {
  assert.match(form, /request-review-readiness/);
  assert.match(form, /Ready to submit/);
  assert.match(form, /Not ready to submit/);
  assert.match(form, /request-review-edit-details/);
  assert.match(form, /request-review-edit-route/);
  assert.match(form, /request-review-edit-cargo/);
  assert.match(form, /request-review-edit-schedule/);
  assert.match(form, /request-review-edit-notes/);
  assert.match(form, /editReviewSection\(2\)/);
  assert.match(form, /editReviewSection\(3\)/);
  assert.match(form, /editReviewSection\(4\)/);
  assert.match(form, /editReviewSection\(5\)/);
  assert.match(form, /request-return-to-review/);
});

test('Review keeps estimate and separates final pricing language', () => {
  assert.match(form, /RequestPricingEstimateCard/);
  assert.match(form, /displayed cost is an estimate/);
  assert.match(form, /final price is calculated when the request is submitted/);
  assert.match(estimateCard, /Estimated shipment cost/);
  assert.equal(form.includes('PricingEngine'), false);
});

test('Submit is guarded, shows Submitting, success only after navigate', () => {
  assert.match(form, /submitInFlight\.current/);
  assert.match(form, /if \(submitInFlight\.current \|\| saveInFlight\.current\) return/);
  assert.match(form, /Submitting…/);
  assert.match(form, /canPressSubmit/);
  assert.match(form, /submitClientShipmentRequest/);
  assert.match(form, /resubmitClientShipmentRequest/);
  assert.match(form, /router\.replace\(/);
  assert.match(form, /submitted=1/);
  assert.match(details, /Request submitted successfully/);
  assert.match(details, /Request resubmitted successfully/);
});

test('Failure preserves draft and maps field errors; unmapped stay visible', () => {
  assert.match(requestForm, /splitApiFieldErrors/);
  assert.match(form, /splitApiFieldErrors/);
  assert.match(form, /couldn't confirm whether submission completed/);
  assert.match(form, /couldn't submit this shipment request/);
  assert.match(form, /requestIdRef/);
  assert.match(form, /validateFormDraft/);
  assert.match(form, /request-save-draft/);
});

test('02\/03 contracts remain intact', () => {
  assert.match(form, /tryContinue/);
  assert.match(form, /validateEditStep/);
  assert.match(stepGate, /validateCargoStep/);
  assert.match(form, /CARGO_TYPE_OPTIONS/);
  assert.match(form, /TRAILER_TYPE_OPTIONS/);
  assert.match(form, /cargoTypeLabel/);
  assert.match(form, /trailerTypeLabel/);
});
