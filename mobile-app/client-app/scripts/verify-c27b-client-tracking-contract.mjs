/**
 * C2.7-B — Client Tracking contract design (static verify).
 * DESIGN ONLY — asserts docs + that the endpoint is still unimplemented.
 * Run: node ./scripts/verify-c27b-client-tracking-contract.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
}

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

let passed = 0;
function check(name, fn) {
  try {
    fn();
    passed += 1;
    console.log(`PASS  ${name}`);
  } catch (e) {
    console.error(`FAIL  ${name}`);
    console.error(e);
    process.exitCode = 1;
  }
}

const docPath = path.join(repoRoot, 'docs/C2.7B_CLIENT_TRACKING_CONTRACT.md');
const doc = fs.existsSync(docPath) ? fs.readFileSync(docPath, 'utf8') : '';
const apiPhp = readRepo('backend/routes/api.php');
const changeStatus = readRepo('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
const hasAudit = readRepo('backend/app/Traits/HasAudit.php');
const trackingTab = readClient('src/app/(tabs)/tracking.tsx');
const statusesSeeder = readRepo('backend/database/seeders/StatusesSeeder.php');

check('contract document exists with required sections', () => {
  assert.ok(fs.existsSync(docPath), 'missing C2.7B contract doc');
  for (const section of [
    'Contract purpose',
    'Endpoint',
    'Authentication',
    'Authorization',
    'Tenant isolation',
    'Request parameters',
    'Path parameter',
    'Response schema',
    'Field-by-field source',
    'Timeline / milestone model',
    'Status vocabulary',
    'HTTP error',
    'Privacy exclusions',
    'GPS exclusion',
    'Security invariants',
    'Example response',
    'Example forbidden',
    'Implementation prerequisites',
    'Open product decisions',
    'Explicit non-goals',
  ]) {
    assert.match(doc, new RegExp(section, 'i'), section);
  }
});

check('endpoint is designed as GET client shipments tracking', () => {
  assert.match(doc, /GET \/api\/v1\/client\/shipments\/\{shipment\}\/tracking/);
});

check('timeline decision is B — derived milestone view', () => {
  assert.match(doc, /B — DERIVED MILESTONE VIEW/);
  assert.match(doc, /Do \*\*not\*\* call it `events`/);
  assert.match(doc, /ChangeShipmentStatusAction/);
  assert.match(doc, /Does not exist|does not exist|No table/i);
});

check('status vocabulary remains raw StatusesSeeder names', () => {
  for (const name of [
    'pending',
    'assigned',
    'in_transit',
    'at_customs',
    'delivered',
    'cancelled',
  ]) {
    assert.match(statusesSeeder, new RegExp(`'name' => '${name}'`));
    assert.match(doc, new RegExp(`\`${name}\``));
  }
  assert.match(doc, /No server-side buckets/);
});

check('auth mirrors client show (404 cross-company, 403 unlinked)', () => {
  assert.match(doc, /client\.shipment\.view/);
  assert.match(doc, /forCustomer/);
  assert.match(doc, /\*\*404\*\*/);
  assert.match(doc, /\*\*403\*\*/);
  assert.match(doc, /customer_company_id === authenticated_user\.company_id|shipment\.customer_company_id === authenticated_user\.company_id/);
});

check('GPS and driver/vehicle excluded from MVP', () => {
  assert.match(doc, /GPS exclusion/i);
  assert.match(doc, /location: null/);
  assert.match(doc, /driver: null/);
  assert.match(doc, /vehicle: null/);
  assert.match(doc, /tracking_logs/);
  assert.match(doc, /live-fleet/);
});

check('example response does not invent status transition events', () => {
  assert.match(doc, /"key": "created"/);
  assert.match(doc, /"key": "pickup"/);
  assert.match(doc, /"key": "delivery"/);
  assert.match(doc, /Not included \(would be fabrication\)/);
  assert.equal(doc.includes('"key": "assigned"'), false);
  assert.equal(doc.includes('"key": "in_transit"'), false);
});

check('designed endpoint is now implemented on backend (C2.7-C); Client UI still unavailable', () => {
  assert.match(apiPhp, /shipments\/\{shipment\}\/tracking/);
  assert.match(apiPhp, /v1\.client\.shipments\.tracking/);
  assert.match(trackingTab, /Redirect/);
});

check('backend evidence: status change does not persist history rows', () => {
  assert.match(changeStatus, /status_id/);
  assert.equal(changeStatus.includes('StatusHistory'), false);
  assert.equal(changeStatus.includes('AuditLog::create'), false);
  assert.match(hasAudit, /created_by/);
  assert.match(hasAudit, /updated_by/);
  assert.equal(hasAudit.includes('AuditLog'), false);
});

check('no shipment status history migration referenced as existing SoT', () => {
  const migrationsDir = path.join(repoRoot, 'backend/database/migrations');
  const files = fs.readdirSync(migrationsDir);
  const historyLike = files.filter((f) =>
    /shipment_status_hist|shipment_event|status_histories/i.test(f)
  );
  assert.equal(historyLike.length, 0, String(historyLike));
  assert.match(doc, /shipment_status_histories|status-event history|TRUE EVENT TIMELINE/i);
});

if (process.exitCode) {
  console.error(`\nC2.7-B verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.7-B verification OK — ${passed} checks passed.`);
}
