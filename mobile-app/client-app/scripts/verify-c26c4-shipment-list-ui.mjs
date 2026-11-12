/**
 * C2.6-C4 Client Shipment List UI — focused verification.
 * Run: node ./scripts/verify-c26c4-shipment-list-ui.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    if (name === 'product-source' || name === 'node_modules') continue;
    const full = path.join(dir, name);
    if (fs.statSync(full).isDirectory()) walk(full, out);
    else if (/\.(ts|tsx)$/.test(name)) out.push(full);
  }
  return out;
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

const screen = read('src/app/(tabs)/shipments.tsx');
const api = read('src/features/shipments/shipmentsApi.ts');
const types = read('src/features/shipments/types.ts');
const home = read('src/app/(tabs)/index.tsx');
const tabs = read('src/app/(tabs)/_layout.tsx');
const messagingApi = read('src/features/messaging/messagingApi.ts');
const srcBlob = walk(path.join(root, 'src'))
  .map((f) => fs.readFileSync(f, 'utf8'))
  .join('\n');

check('uses GET /client/shipments only', () => {
  assert.match(api, /apiClient\.get[^;]*['"`]\/client\/shipments['"`]/);
  assert.equal(api.includes("'/shipments'"), false);
  assert.equal(api.includes('/shipments/queue'), false);
  assert.equal(api.includes('/shipments/kpis'), false);
});

check('does not send customer_company_id or company_id as tenancy', () => {
  assert.match(api, /customer_company_id/);
  assert.match(api, /delete \(query/);
  assert.equal(/\bcompany_id\s*:/.test(api.replace(/customer_company_id/g, '')), false);
});

check('screen wires listClientShipments', () => {
  assert.match(screen, /listClientShipments/);
  assert.match(screen, /shipment-list/);
  assert.match(screen, /shipment-loading/);
  assert.match(screen, /shipment-empty/);
  assert.match(screen, /shipment-error/);
  assert.match(screen, /shipment-retry/);
  assert.match(screen, /shipment-card-/);
});

check('no mock operational data on shipments screen', () => {
  for (const token of ['FX-4920', 'FX-1620', 'Robert Chen', '14:45', 'SafeMap', 'LIVE ETA']) {
    assert.equal(screen.includes(token), false, token);
  }
});

check('navigates to client details only — not tracking/create/ops', () => {
  assert.match(screen, /\/shipment-details\/\$\{item\.id\}/);
  assert.equal(screen.includes("'/tracking'"), false);
  assert.equal(screen.includes('Create Shipment'), false);
  assert.equal(/router\.push\(`\/shipment\/\$\{/.test(screen), false);
  assert.equal(screen.includes("'/shipments/"), false);
});

check('customer-safe status projection; no visible raw status (C3.10/C3.12)', () => {
  assert.match(screen, /customerFacingShipmentStatus/);
  assert.equal(screen.includes('shipment-status-raw-'), false);
  assert.equal(api.includes('function formatStatusLabel'), false);
  assert.match(read('src/features/shipments/shipmentWorkspace.ts'), /customerFacingShipmentStatus/);
  assert.equal(screen.includes('?status=active'), false);
  assert.equal(screen.includes("status: 'In Transit'"), false);
});

check('types cover approved list fields', () => {
  assert.match(types, /shipmentNumber/);
  assert.match(types, /plannedPickupDate/);
  assert.match(types, /pickupCity/);
  assert.match(types, /customerCompany/);
  assert.equal(types.includes('driverPhone'), false);
  assert.equal(types.includes('pickupAddress'), false);
});

check('five-tab IA unchanged', () => {
  assert.match(tabs, /name="shipments"/);
  assert.match(tabs, /tabBarLabel: 'Shipments'/);
});

check('Home consumes client shipment list for overview (C3.3); Shipments tab remains the full list', () => {
  assert.match(home, /listClientShipments/);
  assert.equal(home.includes('home-shipments-unavailable'), false);
});

check('Messaging API module unchanged by shipment list (no client/shipments there)', () => {
  assert.equal(messagingApi.includes('/client/shipments'), false);
  assert.match(messagingApi, /listConversations|sendMessage/);
});

check('ops shipment endpoints absent from client src', () => {
  assert.equal(/apiClient\.get\(\s*['"`]\/shipments['"`]/.test(srcBlob), false);
  assert.equal(srcBlob.includes('/shipments/queue'), false);
  assert.equal(srcBlob.includes('/shipments/kpis'), false);
});

check('unlinked / forbidden states present', () => {
  assert.match(screen, /shipments-unlinked/);
  assert.match(screen, /identityLinked/);
  assert.match(screen, /roleOk/);
});

if (!process.exitCode) {
  console.log(`\nC2.6-C4 shipment list UI checks: ${passed} passed`);
}
