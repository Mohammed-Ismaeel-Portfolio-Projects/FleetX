/**
 * C2.7-A — Client Tracking contract & security audit (static verify).
 * AUDIT ONLY — no implementation assertions beyond "contract missing" + safe Client posture.
 * Run: node ./scripts/verify-c27a-client-tracking-audit.mjs
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

const docPath = path.join(repoRoot, 'docs/C2.7A_CLIENT_TRACKING_CONTRACT_SECURITY_AUDIT.md');
const trackingTab = readClient('src/app/(tabs)/tracking.tsx');
const apiPhp = readRepo('backend/routes/api.php');
const appProvider = readRepo('backend/app/Providers/AppServiceProvider.php');
const shipmentsApi = readClient('src/features/shipments/shipmentsApi.ts');
const home = readClient('src/app/(tabs)/index.tsx');
const liveFleet = readRepo('backend/app/Actions/Tracking/QueryLiveFleetSnapshotAction.php');
const trackingLogAction = readRepo('backend/app/Actions/Tracking/QueryTrackingLogsAction.php');
const trackingStatePolicy = readRepo('backend/app/Policies/TrackingStatePolicy.php');
const dashboardController = readRepo('backend/app/Http/Controllers/DashboardController.php');

check('audit document exists with required sections', () => {
  assert.ok(fs.existsSync(docPath), 'missing C2.7A audit doc');
  const body = fs.readFileSync(docPath, 'utf8');
  for (const section of [
    'Current Client UI state',
    'Existing tracking routes',
    'Existing backend contracts',
    'Auth / AuthZ matrix',
    'Company isolation analysis',
    'Data exposure analysis',
    'Safe vs unsafe endpoints',
    'Safe vs unsafe fields',
    'Existing gaps',
    'Recommended minimum Client Tracking contract',
    'Explicit blockers',
    'Recommended next phase',
  ]) {
    assert.match(body, new RegExp(section, 'i'), section);
  }
});

check('Tracking tab is not a live tracker (C3.2 redirect; no ops APIs)', () => {
  assert.match(trackingTab, /Redirect/);
  assert.equal(trackingTab.includes('live-fleet'), false);
  assert.equal(trackingTab.includes('tracking-logs'), false);
  assert.equal(trackingTab.includes('listClientShipments'), false);
  assert.equal(trackingTab.includes('fetch('), false);
  assert.equal(trackingTab.includes('apiClient'), false);
});

check('Home does not restore Tracking as a primary quick action (C3.3)', () => {
  assert.equal(home.includes('home-qa-tracking'), false);
  assert.equal(home.includes("router.push('/tracking')"), false);
});

check('client tracking route exists under client prefix (C2.7-C); UI still unavailable', () => {
  assert.match(apiPhp, /shipments\/\{shipment\}\/tracking/);
  assert.match(apiPhp, /v1\.client\.shipments\.tracking/);
  const clientBlock = apiPhp.match(/\/\/ Client shipment[\s\S]*?Route::prefix\('client'\)[\s\S]*?\n\s*\}\);/);
  assert.ok(clientBlock, 'client route group present');
  assert.match(clientBlock[0], /tracking/);
  assert.match(clientBlock[0], /shipments\.index/);
  assert.match(clientBlock[0], /shipments\.show/);
  assert.match(trackingTab, /Redirect/);
});

check('ops tracking routes exist and are documented surface', () => {
  assert.match(apiPhp, /tracking-logs/);
  assert.match(apiPhp, /tracking-states/);
  assert.match(apiPhp, /tracking\/live-fleet/);
  assert.match(apiPhp, /shipments\/\{shipment\}\/route/);
});

check('tracking.view gate is admin/dispatcher only', () => {
  assert.match(appProvider, /Gate::define\('tracking\.view'/);
  assert.match(appProvider, /'admin', 'dispatcher'/);
  assert.match(trackingStatePolicy, /admin', 'dispatcher'/);
});

check('live-fleet and tracking-log queries are not customer-scoped', () => {
  assert.match(liveFleet, /Assignment::query\(\)/);
  assert.equal(liveFleet.includes('forCustomer'), false);
  assert.equal(liveFleet.includes('customer_company_id'), false);
  assert.match(trackingLogAction, /TrackingLog::query/);
  assert.equal(trackingLogAction.includes('forCustomer'), false);
});

check('live-fleet exposes driver and GPS-class fields', () => {
  assert.match(liveFleet, /first_name/);
  assert.match(liveFleet, /latitude/);
  assert.match(liveFleet, /plate_number/);
});

check('dashboard controller has no authorize (AuthZ debt note)', () => {
  assert.equal(dashboardController.includes('authorize'), false);
  assert.match(dashboardController, /activity/);
});

check('Client shipment API does not call ops tracking', () => {
  assert.match(shipmentsApi, /\/client\/shipments/);
  assert.equal(shipmentsApi.includes('tracking-logs'), false);
  assert.equal(shipmentsApi.includes('live-fleet'), false);
  assert.equal(shipmentsApi.includes('tracking-states'), false);
  assert.equal(shipmentsApi.includes('/dashboard/'), false);
});

check('proposed minimum contract documented as STATUS+TIMELINE without GPS requirement', () => {
  const body = fs.readFileSync(docPath, 'utf8');
  assert.match(body, /GET \/api\/v1\/client\/shipments\/\{shipment\}\/tracking/);
  assert.match(body, /STATUS \+ TIMELINE/);
  assert.match(body, /no live GPS required/i);
  assert.match(body, /Does a dedicated Client Tracking contract already exist\?/i);
  assert.match(body, /\*\*NO\.\*\*/);
});

check('audit phase did not add client tracking feature module', () => {
  const featuresDir = path.join(clientRoot, 'src/features');
  const names = fs.readdirSync(featuresDir);
  assert.equal(names.includes('tracking'), false);
});

if (process.exitCode) {
  console.error(`\nC2.7-A verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.7-A verification OK — ${passed} checks passed.`);
}
