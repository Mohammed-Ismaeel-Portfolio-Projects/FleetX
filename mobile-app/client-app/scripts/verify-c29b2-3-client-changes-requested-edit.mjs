/**
 * C2.9-B2.3 Static Verification — Client changes_requested Edit Implementation
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

test('implementation doc exists', () => {
  const doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B2\.3/i);
});

test('UpdateClientShipmentRequestAction allows draft and changes_requested', () => {
  const action = read('backend/app/Actions/ShipmentOrder/UpdateClientShipmentRequestAction.php');
  assert.ok(action);
  assert.match(action, /EDITABLE_STATUSES|CHANGES_REQUESTED/);
  assert.match(action, /DRAFT/);
  assert.match(action, /forCustomer/);
  assert.equal(action.includes('TransitionShipmentOrderStatusAction'), false);
});

test('FormRequest still prohibits lifecycle and ownership fields', () => {
  const req = read('backend/app/Http/Requests/Client/UpdateClientShipmentRequestRequest.php');
  assert.ok(req);
  assert.match(req, /status_id.*prohibited/);
  assert.match(req, /submission_revision.*prohibited/);
  assert.match(req, /payload_json.*prohibited/);
  assert.match(req, /submitted_by_user_id.*prohibited/);
  assert.match(req, /dispatcher_notes.*prohibited/);
});

test('client update gate unchanged', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /client\.shipmentRequest\.update/);
});

test('PATCH and resubmit routes exist for review loop', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /v1\.client\.shipment-requests\.update/);
  assert.match(routes, /v1\.client\.shipment-requests\.resubmit/);
});

test('changes_requested update tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestUpdateTest.php');
  assert.ok(testFile);
  assert.match(testFile, /linked_client_can_update_changes_requested/);
  assert.match(testFile, /changes_requested_state_fields_are_preserved/);
  assert.match(testFile, /under_review_request_cannot_be_updated/);
  assert.match(testFile, /patch_changes_requested_then_resubmit_integration/);
});

test('client changes_requested update does not convert or notify', () => {
  const action = read('backend/app/Actions/ShipmentOrder/UpdateClientShipmentRequestAction.php');
  assert.equal(action.includes('Notification'), false);
  assert.equal(action.includes('ConvertShipmentOrderToShipmentAction'), false);
  assert.equal(action.includes('ConvertShipmentOrder'), false);
});

test('TransitionMap not modified for PATCH', () => {
  const map = read('backend/app/Support/ShipmentOrderTransitionMap.php');
  assert.ok(map);
  assert.match(map, /CHANGES_REQUESTED.*SUBMITTED/s);
});

test('audit doc preserved for traceability', () => {
  const audit = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_AUDIT.md');
  assert.ok(audit);
  assert.match(audit, /PASS WITH FINDINGS/i);
});
