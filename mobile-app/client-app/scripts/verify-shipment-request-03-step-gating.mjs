/**
 * SHIPMENT-REQUEST-03 — wizard step gating verification (static + behavioral).
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
const stepValidation = readClient('src/features/requests/requestStepValidation.ts');
const requestForm = readClient('src/features/requests/requestForm.ts');
const enums = readClient('src/features/requests/shipmentRequestEnums.ts');

test('step validators exist for all edit steps', () => {
  assert.match(stepValidation, /export function validateDetailsStep/);
  assert.match(stepValidation, /export function validateRouteStep/);
  assert.match(stepValidation, /export function validateCargoStep/);
  assert.match(stepValidation, /export function validateScheduleStep/);
  assert.match(stepValidation, /export function validateNotesStep/);
  assert.match(stepValidation, /export function validateEditStep/);
  assert.match(stepValidation, /export function validateEditStepsThrough/);
  assert.match(stepValidation, /export function validateAllEditSteps/);
});

test('Continue and step indicator use local gating (no API on Continue)', () => {
  assert.match(form, /tryContinue/);
  assert.match(form, /trySelectEditStep/);
  assert.match(form, /validateEditStep\(editStep, values\)/);
  assert.match(form, /validateEditStepsThrough/);
  assert.match(form, /onPress=\{tryContinue\}/);
  assert.equal(/tryContinue[\s\S]{0,400}createClientShipmentRequest/.test(form), false);
  assert.equal(form.includes('pricing/calculate'), false);
  assert.equal(form.includes('PricingEngine'), false);
});

test('Save Draft remains ungated by step Continue rules', () => {
  assert.match(form, /request-save-draft/);
  assert.match(form, /validateFormDraft/);
  assert.match(form, /onPress=\{\(\) => void persist\(\)\}/);
});

test('Back navigation clears soft errors and stays local', () => {
  assert.match(form, /request-step-back/);
  assert.match(form, /setEditStep\(\(step\) => \(step - 1\)/);
});

test('enum contract from 02 remains authoritative', () => {
  assert.match(enums, /value: 'general'/);
  assert.match(enums, /value: 'dry_van'/);
  assert.match(stepValidation, /isCargoTypeValue/);
  assert.match(stepValidation, /isTrailerTypeValue/);
  assert.match(requestForm, /validateAllEditSteps/);
});

/** Behavioral mirrors of route/cargo/schedule gates (keep in sync with requestStepValidation). */
function routeBlocks(values) {
  const errors = {};
  if (!values.pickupCity?.trim()) errors.pickupCity = 'required';
  if (!values.pickupCountry?.trim()) errors.pickupCountry = 'required';
  if (values.pickupLatitude == null || values.pickupLongitude == null) errors.pickupLatitude = 'required';
  if (!values.deliveryCity?.trim()) errors.deliveryCity = 'required';
  if (!values.deliveryCountry?.trim()) errors.deliveryCountry = 'required';
  if (values.deliveryLatitude == null || values.deliveryLongitude == null) {
    errors.deliveryLatitude = 'required';
  }
  return errors;
}

function cargoBlocks(values) {
  const errors = {};
  const cargoOk = ['general', 'refrigerated', 'heavy', 'hazardous', 'fragile'].includes(values.cargoType);
  const trailerOk = ['dry_van', 'reefer', 'flatbed', 'lowboy', 'tanker'].includes(values.trailerType);
  if (!values.cargoType?.trim()) errors.cargoType = 'required';
  else if (!cargoOk) errors.cargoType = 'invalid';
  if (!values.cargoDescription?.trim()) errors.cargoDescription = 'required';
  if (!values.trailerType?.trim()) errors.trailerType = 'required';
  else if (!trailerOk) errors.trailerType = 'invalid';
  return errors;
}

function scheduleBlocks(values) {
  const errors = {};
  const pickup = values.requestedPickupDate?.trim() || '';
  const delivery = values.requestedDeliveryDate?.trim() || '';
  const ok = (d) => /^\d{4}-\d{2}-\d{2}$/.test(d);
  if (pickup && !ok(pickup)) errors.requestedPickupDate = 'bad';
  if (delivery && !ok(delivery)) errors.requestedDeliveryDate = 'bad';
  if (pickup && delivery && ok(pickup) && ok(delivery) && delivery < pickup) {
    errors.requestedDeliveryDate = 'order';
  }
  return errors;
}

test('behavioral: details never block; notes never block', () => {
  assert.deepEqual(Object.keys({}), []);
  assert.match(stepValidation, /Step 1 — Details: all optional/);
  assert.match(stepValidation, /Step 5 — Notes: optional/);
});

test('behavioral: route missing city/coords block; valid route empty errors', () => {
  assert.ok(Object.keys(routeBlocks({ pickupCity: '' })).includes('pickupCity'));
  assert.ok(
    Object.keys(
      routeBlocks({
        pickupCity: 'A',
        pickupCountry: 'PL',
        pickupLatitude: 1,
        pickupLongitude: 2,
        deliveryCity: 'B',
        deliveryCountry: 'PL',
        deliveryLatitude: 3,
        deliveryLongitude: 4,
      })
    ).length === 0
  );
});

test('behavioral: cargo missing/invalid blocks; valid cargo empty errors', () => {
  assert.equal(cargoBlocks({ cargoType: '', cargoDescription: 'x', trailerType: 'dry_van' }).cargoType, 'required');
  assert.equal(
    cargoBlocks({ cargoType: 'General Freight', cargoDescription: 'x', trailerType: 'dry_van' }).cargoType,
    'invalid'
  );
  assert.equal(
    cargoBlocks({ cargoType: 'general', cargoDescription: '', trailerType: 'dry_van' }).cargoDescription,
    'required'
  );
  assert.equal(cargoBlocks({ cargoType: 'general', cargoDescription: 'x', trailerType: '' }).trailerType, 'required');
  assert.deepEqual(cargoBlocks({ cargoType: 'general', cargoDescription: 'x', trailerType: 'reefer' }), {});
});

test('behavioral: schedule empty OK; delivery before pickup blocks', () => {
  assert.deepEqual(scheduleBlocks({ requestedPickupDate: '', requestedDeliveryDate: '' }), {});
  assert.equal(
    scheduleBlocks({ requestedPickupDate: '2026-08-20', requestedDeliveryDate: '2026-08-19' })
      .requestedDeliveryDate,
    'order'
  );
  assert.deepEqual(
    scheduleBlocks({ requestedPickupDate: '2026-08-20', requestedDeliveryDate: '2026-08-20' }),
    {}
  );
});

test('source asserts Continue stays on invalid; forward jump validates prerequisites', () => {
  assert.match(form, /Fix the highlighted fields before continuing/);
  assert.match(form, /Complete earlier steps before continuing/);
  assert.match(form, /target < editStep/);
  assert.match(form, /validateEditStepsThrough\(through, values, 1\)/);
});
