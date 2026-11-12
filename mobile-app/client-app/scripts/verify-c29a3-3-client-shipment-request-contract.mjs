/**
 * C2.9-A3.3 Static Verification — Client Shipment Request API Contract
 *
 * Verifies the design document exists and contains required contract decisions.
 * Does NOT verify implementation (C2.9-A3.3 is design-only).
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');
const DOC = resolve(ROOT, 'docs', 'C2.9A3_3_CLIENT_SHIPMENT_REQUEST_API_CONTRACT.md');

function readDoc() {
  if (!existsSync(DOC)) return null;
  return readFileSync(DOC, 'utf8');
}

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

test('contract document exists', () => {
  const doc = readDoc();
  assert.ok(doc, 'C2.9A3_3_CLIENT_SHIPMENT_REQUEST_API_CONTRACT.md must exist');
});

test('document is DESIGN ONLY / not implementation', () => {
  const doc = readDoc();
  assert.match(doc, /DESIGN ONLY/i);
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /NOT IMPLEMENTED in C2\.9-A3\.3/i);
});

test('proposed endpoints are documented', () => {
  const doc = readDoc();
  assert.match(doc, /Endpoint Matrix/i);
  assert.match(doc, /GET.*\/api\/v1\/client\/shipment-requests/i);
  assert.match(doc, /POST.*\/api\/v1\/client\/shipment-requests/i);
  assert.match(doc, /PATCH.*\/api\/v1\/client\/shipment-requests/i);
  assert.match(doc, /\/submit/i);
  assert.match(doc, /\/resubmit/i);
});

test('client ownership restrictions are documented', () => {
  const doc = readDoc();
  assert.match(doc, /Ownership/i);
  assert.match(doc, /customer_company_id/);
  assert.match(doc, /SERVER|server-derived|never in client body/i);
  assert.match(doc, /FORBIDDEN|prohibited|denylist/i);
});

test('lifecycle capability matrix exists', () => {
  const doc = readDoc();
  assert.match(doc, /State Capabilities|capability matrix/i);
  assert.match(doc, /draft.*submitted|draft → submitted/i);
  assert.match(doc, /changes_requested.*submitted|changes_requested → submitted/i);
  assert.match(doc, /Client-forbidden|forbidden transitions/i);
});

test('draft submit resubmit semantics exist', () => {
  const doc = readDoc();
  assert.match(doc, /Draft Semantics/i);
  assert.match(doc, /Submit Semantics/i);
  assert.match(doc, /Resubmit Semantics/i);
  assert.match(doc, /TransitionShipmentOrderStatusAction/);
});

test('revision semantics exist', () => {
  const doc = readDoc();
  assert.match(doc, /Revision Semantics/i);
  assert.match(doc, /submission_revision/);
  assert.match(doc, /revision.*0|submission_revision = 0/i);
});

test('payload_json semantics exist', () => {
  const doc = readDoc();
  assert.match(doc, /payload_json.*Semantics|Semantics.*payload_json/i);
  assert.match(doc, /payload_json/);
  assert.match(doc, /snapshot|Snapshot/i);
});

test('client writable allowlist exists', () => {
  const doc = readDoc();
  assert.match(doc, /Client-Writable|client-writable|CLIENT-WRITABLE/i);
  assert.match(doc, /cargo_type/);
  assert.match(doc, /pickup_city/);
  assert.match(doc, /delivery_city/);
});

test('server-owned denylist exists', () => {
  const doc = readDoc();
  assert.match(doc, /Server-Owned|server-owned|denylist/i);
  assert.match(doc, /status_id/);
  assert.match(doc, /converted_shipment_id/);
  assert.match(doc, /request_number/);
});

test('security rules exist', () => {
  const doc = readDoc();
  assert.match(doc, /Security Analysis|Security/i);
  assert.match(doc, /IDOR/i);
  assert.match(doc, /tenant|Tenant/i);
  assert.match(doc, /mass assignment|Mass assignment/i);
});

test('dispatcher compatibility is documented', () => {
  const doc = readDoc();
  assert.match(doc, /Dispatcher Compatibility/i);
  assert.match(doc, /request_number/);
  assert.match(doc, /submitted_by_user_id|submitter/i);
});

test('conversion is explicitly deferred', () => {
  const doc = readDoc();
  assert.match(doc, /Conversion Boundary|conversion/i);
  assert.match(doc, /NOT IMPLEMENTED|deferred|DEFERRED/i);
  assert.match(doc, /ConvertShipmentOrderToShipmentAction|conversion action/i);
});

test('notification writers are explicitly deferred', () => {
  const doc = readDoc();
  assert.match(doc, /Notification Future|notification/i);
  assert.match(doc, /NOT IMPLEMENTED/i);
  assert.match(doc, /deferred|DEFERRED/i);
});

test('contract design document remains valid', () => {
  const doc = readDoc();
  assert.ok(doc);
  assert.match(doc, /DESIGN ONLY/i);
});

test('write endpoints not implemented beyond read create draft update submit and resubmit', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.equal(/Route::delete\([^)]*shipment-requests/i.test(routes), false);
});

test('client shipment request UI exists without conversion controls', () => {
  const form = read('mobile-app/client-app/src/app/request-form.tsx') || '';
  assert.ok(form.length > 0, 'Client request form expected after SHIPMENT-REQUEST phases');
  assert.equal(/convertToShipment|ConvertShipment/i.test(form), false);
});

test('editable-state matrix exists', () => {
  const doc = readDoc();
  assert.match(doc, /Editable-State|editable-state|Editable-State Matrix/i);
  assert.match(doc, /under_review/i);
  assert.match(doc, /immutable|Immutable/i);
});

test('address policy is documented', () => {
  const doc = readDoc();
  assert.match(doc, /Address Policy/i);
  assert.match(doc, /pickup_address|street|address/i);
});

test('implementation prerequisites exist', () => {
  const doc = readDoc();
  assert.match(doc, /Implementation Prerequisites/i);
  assert.match(doc, /TransitionShipmentOrderStatusAction/);
});
