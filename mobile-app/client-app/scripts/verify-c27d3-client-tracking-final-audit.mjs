/**
 * C2.7-D3 — Client Tracking MVP final audit (static verification only).
 * AUDIT ONLY — does not change application behavior.
 * Run: node ./scripts/verify-c27d3-client-tracking-final-audit.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientRoot = path.join(__dirname, '..');
const repoRoot = path.join(clientRoot, '..', '..');

function readClient(rel) {
  return fs.readFileSync(path.join(clientRoot, rel), 'utf8');
}

function readRepo(rel) {
  return fs.readFileSync(path.join(repoRoot, rel), 'utf8');
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

const list = readClient('src/app/(tabs)/shipments.tsx');
const details = readClient('src/app/shipment-details/[id].tsx');
const progress = readClient('src/app/shipment-tracking/[id].tsx');
const trackingTab = readClient('src/app/(tabs)/tracking.tsx');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const types = readClient('src/features/shipments/types.ts');
const layout = readClient('src/app/_layout.tsx');
const routes = readRepo('backend/routes/api.php');
const action = readRepo('backend/app/Actions/Shipment/GetClientShipmentTrackingAction.php');
const resource = readRepo('backend/app/Http/Resources/Client/ClientShipmentTrackingResource.php');
const featureTest = readRepo('backend/tests/Feature/Shipment/ClientShipmentTrackingTest.php');
const auditDoc = path.join(repoRoot, 'docs/C2.7D3_CLIENT_TRACKING_FINAL_AUDIT.md');

check('final audit document exists with required sections', () => {
  assert.ok(fs.existsSync(auditDoc));
  const body = fs.readFileSync(auditDoc, 'utf8');
  for (const section of [
    'Executive result',
    'Scope audited',
    'Customer flow verification',
    'Navigation verification',
    'API boundary verification',
    'Security verification',
    'Data semantics verification',
    'UX state verification',
    'Request/concurrency verification',
    'Accessibility verification',
    'Architectural boundary verification',
    'Test evidence',
    'Findings',
    'Deferred / future work',
    'Final recommendation',
  ]) {
    assert.match(body, new RegExp(section, 'i'), section);
  }
  assert.match(body, /PASS WITH FINDINGS|PASS\b/);
});

check('customer flow: list → details → progress IDs and APIs', () => {
  assert.match(list, /listClientShipments/);
  assert.match(list, /router\.push\(`\/shipment-details\/\$\{item\.id\}`\)/);
  assert.match(details, /getClientShipment/);
  assert.match(details, /router\.push\(`\/shipment-tracking\/\$\{shipmentId\}`\)/);
  assert.match(progress, /getClientShipmentTracking/);
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.match(layout, /shipment-tracking\/\[id\]/);
});

check('tracking tab redirects to Shipments (C3.2; no live selection)', () => {
  assert.match(trackingTab, /Redirect/);
  assert.equal(trackingTab.includes('getClientShipmentTracking'), false);
  assert.equal(trackingTab.includes('listClientShipments'), false);
});

check('API boundary: client tracking only; no ops endpoints in client feature path', () => {
  const trackingFn = api.slice(api.indexOf('export async function getClientShipmentTracking'));
  assert.match(trackingFn, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/tracking/);
  assert.equal(trackingFn.includes('params:'), false);
  assert.equal(api.includes('tracking-logs'), false);
  assert.equal(api.includes('tracking-states'), false);
  assert.equal(api.includes('live-fleet'), false);
  assert.equal(progress.includes('live-fleet'), false);
  assert.equal(list.includes('live-fleet'), false);
  assert.equal(details.includes('live-fleet'), false);
  assert.match(routes, /v1\.client\.shipments\.tracking/);
});

check('navigation does not use handoff or bare tracking for progress', () => {
  assert.equal(/router\.push\(`\/shipment\/\$\{/.test(list), false);
  assert.equal(/router\.push\(`\/shipment\/\$\{/.test(details), false);
  assert.match(progress, /router\.back\(\)/);
  assert.match(progress, /shipment-details\/\$\{shipmentId\}/);
  assert.equal(/router\.(push|replace)\([`'"]\/tracking['"`]/.test(progress), false);
});

check('security: scoped action + forbidden fields + feature matrix present', () => {
  assert.match(action, /forCustomer\(\$companyId\)/);
  assert.match(action, /company_id/);
  // Payload keys only (docblock may mention excluded concepts).
  const payloadStart = resource.indexOf('return [');
  const payloadEnd = resource.indexOf('];', payloadStart);
  assert.ok(payloadStart > 0 && payloadEnd > payloadStart);
  const payload = resource.slice(payloadStart, payloadEnd);
  // Top-level GPS keys remain forbidden; nested last_known_position is the A3/C5.1 allowlist.
  assert.equal(/^\s*'latitude'\s*=>/m.test(payload), false);
  assert.equal(/^\s*'longitude'\s*=>/m.test(payload), false);
  assert.match(payload, /last_known_position/);
  assert.equal(/['"]driver['"]/.test(payload), false);
  assert.equal(/['"]vehicle['"]/.test(payload), false);
  assert.equal(/['"]updated_at['"]/.test(payload), false);
  assert.match(resource, /derivedMilestones/);
  assert.match(resource, /No driver, vehicle/i);
  assert.match(resource, /last_known_position is a three-field projection/i);
  assert.match(featureTest, /assertUnauthorized/);
  assert.match(featureTest, /assertForbidden/);
  assert.match(featureTest, /assertNotFound/);
  assert.match(featureTest, /assertStatus\(422\)/);
  assert.match(featureTest, /another_company_shipment_returns_404/);
  assert.match(featureTest, /soft_deleted_shipment_returns_404/);
  assert.match(featureTest, /ops_tracking_endpoints_remain_forbidden/);
  assert.match(featureTest, /response_excludes_forbidden/);
});

check('data semantics: milestones not status history; types allowlist', () => {
  assert.match(progress, /Shipment Progress/);
  assert.match(progress, /Milestones/);
  assert.equal(progress.includes('Status History'), false);
  assert.equal(progress.includes('Tracking History'), false);
  assert.match(types, /ClientShipmentTracking/);
  assert.match(types, /'created' \| 'pickup' \| 'delivery'/);
  assert.match(progress, /not a status-event history/i);
  assert.equal(progress.includes('completion_percentage'), false);
});

check('request safety present on list, details, progress', () => {
  for (const body of [list, details, progress]) {
    assert.match(body, /inFlightRef/);
    assert.match(body, /requestSeqRef/);
  }
  assert.match(progress, /canRetry = !isForbidden && !isNotFound/);
  assert.match(details, /statusCode === 401/);
  assert.match(progress, /statusCode === 401/);
});

check('phase is audit-only deliverables (doc + verify script)', () => {
  assert.ok(fs.existsSync(path.join(clientRoot, 'scripts/verify-c27d3-client-tracking-final-audit.mjs')));
  assert.match(fs.readFileSync(auditDoc, 'utf8'), /AUDIT \/ VERIFICATION ONLY|AUDIT ONLY/i);
});

if (process.exitCode) {
  console.error(`\nC2.7-D3 verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.7-D3 verification OK — ${passed} checks passed.`);
}
