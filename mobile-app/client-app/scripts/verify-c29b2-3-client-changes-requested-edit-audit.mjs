/**
 * C2.9-B2.3 Static Verification — Client changes_requested Edit Audit
 * Audit-only phase: confirms documentation and identifies the minimal implementation gap.
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

test('audit document exists with required phase id', () => {
  const doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B2\.3/i);
  assert.match(doc, /AUDIT ONLY|audit only/i);
});

test('audit identifies UpdateClientShipmentRequestAction as the gap', () => {
  const doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_AUDIT.md');
  assert.match(doc, /UpdateClientShipmentRequestAction/);
  assert.match(doc, /changes_requested/);
  assert.match(doc, /PASS WITH FINDINGS/i);
});

test('audit confirms FormRequest and gate are ready without change', () => {
  const doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_AUDIT.md');
  assert.match(doc, /UpdateClientShipmentRequestRequest/);
  assert.match(doc, /client\.shipmentRequest\.update/);
  assert.match(doc, /No changes required.*FormRequest|Ready/i);
});

test('audit documents PATCH then resubmit loop', () => {
  const doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_AUDIT.md');
  assert.match(doc, /PATCH.*resubmit|resubmit/i);
  assert.match(doc, /ResubmitClientShipmentRequestAction/);
});

test('action allows draft and changes_requested after B2.3 implementation', () => {
  const action = read('backend/app/Actions/ShipmentOrder/UpdateClientShipmentRequestAction.php');
  assert.ok(action);
  assert.match(action, /DRAFT/);
  assert.match(action, /CHANGES_REQUESTED/);
});

test('FormRequest already prohibits lifecycle and ownership fields', () => {
  const req = read('backend/app/Http/Requests/Client/UpdateClientShipmentRequestRequest.php');
  assert.ok(req);
  assert.match(req, /status_id.*prohibited/);
  assert.match(req, /submission_revision.*prohibited/);
  assert.match(req, /payload_json.*prohibited/);
  assert.match(req, /submitted_by_user_id.*prohibited/);
  assert.match(req, /dispatcher_notes.*prohibited/);
});

test('PATCH route and resubmit route both exist for client loop', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /v1\.client\.shipment-requests\.update/);
  assert.match(routes, /v1\.client\.shipment-requests\.resubmit/);
});

test('dispatcher request-changes route exists for review loop entry', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /request-changes/);
});

test('A3.3 contract assigns PATCH yes for changes_requested', () => {
  const contract = read('docs/C2.9A3_3_CLIENT_SHIPMENT_REQUEST_API_CONTRACT.md');
  assert.ok(contract);
  assert.match(contract, /changes_requested.*Yes.*PATCH|PATCH.*changes_requested/i);
});

test('B2.3 implementation completed after audit', () => {
  const action = read('backend/app/Actions/ShipmentOrder/UpdateClientShipmentRequestAction.php');
  const impl = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_IMPLEMENTATION.md');
  assert.match(action, /CHANGES_REQUESTED/);
  assert.ok(impl);
});

test('client update tests include changes_requested coverage after B2.3', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ClientShipmentRequestUpdateTest.php');
  assert.ok(testFile);
  assert.match(testFile, /submitted_request_cannot_be_updated/);
  assert.match(testFile, /linked_client_can_update_changes_requested/);
});

test('A3.4.3a verifier recognizes expanded PATCH scope after B2.3', () => {
  const verifier = read('mobile-app/client-app/scripts/verify-c29a3-4-3a-client-shipment-request-update.mjs');
  assert.ok(verifier);
  assert.match(verifier, /CHANGES_REQUESTED/);
});

test('no conversion or dispatcher PATCH introduced', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.equal(routes.includes('/convert'), false);
  const opsPatch = routes
    .split('\n')
    .filter((line) => /\[ShipmentRequestController::class/.test(line) && /patch/i.test(line))
    .join('\n');
  assert.equal(opsPatch.length, 0);
});

test('audit recommends explicit two-status allowlist', () => {
  const doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_AUDIT.md');
  assert.match(doc, /CHANGES_REQUESTED/);
  assert.match(doc, /allowlist|allow list/i);
});

test('audit certifies safe to proceed', () => {
  const doc = read('docs/C2.9B2_3_CLIENT_CHANGES_REQUESTED_EDIT_AUDIT.md');
  assert.match(doc, /safe to proceed/i);
});
