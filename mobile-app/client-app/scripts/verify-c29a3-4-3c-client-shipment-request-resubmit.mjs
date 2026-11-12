/**
 * C2.9-A3.4.3c Static Verification — Client Shipment Request Resubmit
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

test('resubmit implementation doc exists', () => {
  const doc = read('docs/C2.9A3_4_3C_CLIENT_SHIPMENT_REQUEST_RESUBMIT_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-A3\.4\.3c/i);
});

test('POST resubmit route exists with correct name', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /\/resubmit/);
  assert.match(routes, /v1\.client\.shipment-requests\.resubmit/);
});

test('ResubmitClientShipmentRequestAction uses TransitionShipmentOrderStatusAction', () => {
  const action = read('backend/app/Actions/ShipmentOrder/ResubmitClientShipmentRequestAction.php');
  assert.ok(action);
  assert.match(action, /class ResubmitClientShipmentRequestAction/);
  assert.match(action, /TransitionShipmentOrderStatusAction/);
  assert.match(action, /forCustomer/);
  assert.match(action, /CHANGES_REQUESTED/);
  assert.match(action, /submission_revision \+ 1|submission_revision \+\s*1/);
});

test('ResubmitClientShipmentRequestRequest prohibits client lifecycle fields', () => {
  const req = read('backend/app/Http/Requests/Client/ResubmitClientShipmentRequestRequest.php');
  assert.ok(req);
  assert.match(req, /payload_json.*prohibited/);
  assert.match(req, /submitted_by_user_id.*prohibited/);
  assert.match(req, /submission_revision.*prohibited/);
  assert.match(req, /reason.*prohibited/);
});

test('reuses submission validator and payload snapshot builder', () => {
  const action = read('backend/app/Actions/ShipmentOrder/ResubmitClientShipmentRequestAction.php');
  assert.match(action, /ShipmentOrderSubmissionValidator/);
  assert.match(action, /ShipmentOrderPayloadSnapshotBuilder/);
});

test('client.shipmentRequest.resubmit gate exists', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /client\.shipmentRequest\.resubmit/);
});

test('resubmit feature tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestResubmitTest.php');
  assert.ok(testFile);
  assert.match(testFile, /ClientShipmentRequestResubmitTest/);
  assert.match(testFile, /resubmit_persists_metadata_and_history/);
  assert.match(testFile, /dispatcher_reason_history_remains_intact/);
});

test('client resubmit does not invoke conversion', () => {
  const action = read('backend/app/Actions/ShipmentOrder/ResubmitClientShipmentRequestAction.php') || '';
  assert.ok(action);
  assert.equal(action.includes('ConvertShipmentOrderToShipmentAction'), false);
  assert.equal(action.includes('ConvertShipmentOrder'), false);
});

test('client shipment request UI exists without conversion controls', () => {
  const form = read('mobile-app/client-app/src/app/request-form.tsx') || '';
  assert.ok(form.length > 0, 'Client request form expected after SHIPMENT-REQUEST phases');
  assert.equal(/convertToShipment|ConvertShipment/i.test(form), false);
});

test('scope limited to changes_requested resubmit only', () => {
  const doc = read('docs/C2.9A3_4_3C_CLIENT_SHIPMENT_REQUEST_RESUBMIT_IMPLEMENTATION.md');
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /changes_requested/i);
});
