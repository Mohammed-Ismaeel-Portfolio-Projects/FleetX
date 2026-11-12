/**
 * C2.7-D2 — Client Tracking UX hardening verification (static).
 * Run: node ./scripts/verify-c27d2-client-tracking-hardening.mjs
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

const progress = readClient('src/app/shipment-tracking/[id].tsx');
const api = readClient('src/features/shipments/shipmentsApi.ts');
const trackingTab = readClient('src/app/(tabs)/tracking.tsx');
const list = readClient('src/app/(tabs)/shipments.tsx');
const details = readClient('src/app/shipment-details/[id].tsx');
const layout = readClient('src/app/_layout.tsx');
const backendRoute = readRepo('backend/routes/api.php');
const trackingFn = api.slice(api.indexOf('export async function getClientShipmentTracking'));

check('correct route and client tracking endpoint', () => {
  assert.match(layout, /shipment-tracking\/\[id\]/);
  assert.ok(fs.existsSync(path.join(clientRoot, 'src/app/shipment-tracking/[id].tsx')));
  assert.match(progress, /getClientShipmentTracking/);
  assert.match(trackingFn, /\/client\/shipments\/\$\{encodeURIComponent\(id\)\}\/tracking/);
  assert.match(backendRoute, /v1\.client\.shipments\.tracking/);
  assert.equal(trackingFn.includes('params:'), false);
});

check('shipment ID only — no company/tenant/ops params', () => {
  assert.equal(/params:\s*\{[^}]*company_id/.test(trackingFn), false);
  assert.equal(/params:\s*\{[^}]*customer_company_id/.test(trackingFn), false);
  assert.equal(trackingFn.includes('driver_id'), false);
  assert.equal(trackingFn.includes('vehicle_id'), false);
  assert.equal(progress.includes('company_id:'), false);
  assert.equal(progress.includes('customer_company_id'), false);
});

check('no ops tracking / live GPS / driver / vehicle / ETA', () => {
  assert.equal(progress.includes('tracking-logs'), false);
  assert.equal(progress.includes('tracking-states'), false);
  assert.equal(progress.includes('live-fleet'), false);
  assert.equal(progress.includes('SafeMap'), false);
  assert.equal(progress.includes('react-native-maps'), false);
  // C5.1: allowlisted last-known position coords are customer-safe; live GPS is not.
  assert.match(progress, /Last known location/);
  assert.match(progress, /not a live GPS feed/i);
  assert.equal(progress.includes('live location'), false);
  assert.equal(progress.includes('plate_number'), false);
  assert.equal(progress.includes('driver_id'), false);
  assert.equal(progress.includes('vehicle_id'), false);
  assert.equal(api.includes('tracking-logs'), false);
  assert.equal(api.includes('live-fleet'), false);
});

check('no fake progress percentage or status-history wording', () => {
  assert.equal(progress.includes('Status History'), false);
  assert.equal(progress.includes('Status Events'), false);
  assert.equal(progress.includes('Tracking History'), false);
  assert.equal(/%/.test(progress.replace(/encodeURIComponent|100/g, '')), false);
  assert.equal(progress.includes('progressBar'), false);
  assert.equal(progress.includes('completion_percentage'), false);
  assert.match(progress, /Shipment Progress/);
  assert.match(progress, /Milestones/);
  assert.match(progress, /not a status-event history/i);
});

check('approved states exist', () => {
  assert.match(progress, /shipment-progress-loading/);
  assert.match(progress, /shipment-progress-screen/);
  assert.match(progress, /shipment-progress-forbidden/);
  assert.match(progress, /shipment-progress-not-found/);
  assert.match(progress, /shipment-progress-error/);
  assert.match(progress, /shipment-progress-retry/);
  assert.match(progress, /statusCode === 401/);
  assert.match(progress, /AuthContext|do not reinterpret/);
  assert.match(progress, /shipment-progress-unlinked/);
  assert.match(progress, /shipment-progress-forbidden-role/);
  assert.match(progress, /errorIsNetwork/);
});

check('retry only for retryable failures; 403/404 have no retry', () => {
  assert.match(progress, /canRetry = !isForbidden && !isNotFound/);
  assert.match(progress, /errorStatus === 403/);
  assert.match(progress, /errorStatus === 404/);
  // Forbidden/not-found branches must not render retry when canRetry is false
  assert.match(progress, /\{canRetry \? \(/);
});

check('safe back navigation', () => {
  assert.match(progress, /router\.canGoBack\(\)/);
  assert.match(progress, /router\.back\(\)/);
  assert.match(progress, /shipment-details\/\$\{shipmentId\}/);
  assert.equal(/router\.(push|replace)\([`'"]\/shipment\/\$\{/.test(progress), false);
  assert.equal(/router\.(push|replace)\([`'"]\/tracking['"`]/.test(progress), false);
  assert.equal(/router\.(push|replace)\([`'"]\/shipment-details['"`]\)/.test(progress), false);
});

check('stale/duplicate request protection', () => {
  assert.match(progress, /inFlightRef/);
  assert.match(progress, /requestSeqRef/);
  assert.match(progress, /if \(inFlightRef\.current\) return/);
  assert.match(progress, /requestSeq !== requestSeqRef\.current/);
  assert.match(progress, /shipmentIdRef\.current !== idToLoad/);
  assert.match(progress, /drop stale tracking|Clear any prior success payload/i);
});

check('accessibility labels exist', () => {
  assert.match(progress, /accessibilityLabel="Go back to shipment details"/);
  assert.match(progress, /accessibilityHint/);
  assert.match(progress, /accessibilityLabel=\{`Shipment progress for/);
  assert.match(progress, /accessibilityLabel=\{/);
  assert.match(progress, /customerFacingShipmentStatus/);
  assert.equal(progress.includes('shipment-progress-status-raw'), false);
  assert.match(progress, /accessibilityLabel=\{`Route from/);
  assert.match(progress, /accessibilityLabel="Shipment progress milestones"/);
  assert.match(progress, /accessibilityRole="button"/);
});

check('Tracking tab redirects to Shipments (C3.2); no card Track', () => {
  assert.match(trackingTab, /Redirect/);
  assert.equal(trackingTab.includes('getClientShipmentTracking'), false);
  assert.equal(list.includes('shipment-tracking'), false);
  assert.equal(/\bTrack\b/.test(list), false);
  assert.match(details, /shipment-details-progress/);
  assert.match(details, /Shipment progress/);
});

check('hierarchy: identity, status, route, milestones', () => {
  assert.match(progress, /shipment-progress-number/);
  assert.match(progress, /shipment-progress-status/);
  assert.match(progress, /customerFacingShipmentStatus/);
  assert.equal(progress.includes('shipment-progress-status-raw'), false);
  assert.match(progress, /shipment-progress-route/);
  assert.match(progress, /shipment-progress-origin/);
  assert.match(progress, /shipment-progress-destination/);
  assert.match(progress, /shipment-progress-milestones/);
  assert.match(progress, /MILESTONE_ORDER|sortMilestones/);
  assert.match(progress, /Planned/);
  assert.match(progress, /Actual/);
  assert.match(progress, /Occurred/);
});

if (process.exitCode) {
  console.error(`\nC2.7-D2 verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.7-D2 verification OK — ${passed} checks passed.`);
}
