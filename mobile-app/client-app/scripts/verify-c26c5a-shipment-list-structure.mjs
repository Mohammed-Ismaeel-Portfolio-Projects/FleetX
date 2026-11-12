/**
 * C2.6-C5-A Client Shipment List Structure — focused verification.
 * Run: node ./scripts/verify-c26c5a-shipment-list-structure.mjs
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
const messagingApi = read('src/features/messaging/messagingApi.ts');
const messagingFiles = [
  'src/features/messaging/messagingApi.ts',
  'src/features/messaging/types.ts',
  'src/app/(tabs)/messages.tsx',
  'src/app/conversation/[id].tsx',
].filter((rel) => fs.existsSync(path.join(root, rel)));

check('correct client endpoint referenced', () => {
  assert.match(api, /['"`]\/client\/shipments['"`]/);
  assert.match(api, /listClientShipments/);
  assert.match(screen, /listClientShipments/);
});

check('no ops shipment endpoint referenced', () => {
  assert.equal(api.includes("'/shipments'"), false);
  assert.equal(api.includes('"/shipments"'), false);
  assert.equal(api.includes('/shipments/queue'), false);
  assert.equal(api.includes('/shipments/kpis'), false);
  assert.equal(screen.includes('/shipments/queue'), false);
  assert.equal(screen.includes('/shipments/kpis'), false);
});

check('no company scope parameter sent', () => {
  assert.match(api, /delete \(query as \{ customer_company_id/);
  assert.match(api, /delete \(query as \{ company_id/);
  assert.equal(screen.includes('customer_company_id'), false);
  assert.equal(screen.includes('company_id:'), false);
  assert.equal(screen.includes('driver_id'), false);
  assert.equal(screen.includes('dispatcher_id'), false);
});

check('no mock shipment data', () => {
  for (const token of ['FX-4920', 'FX-1620', 'Robert Chen', '14:45', 'SafeMap', 'LIVE ETA', 'mockShipments']) {
    assert.equal(screen.includes(token), false, token);
  }
});

check('list has real-data path', () => {
  assert.match(screen, /listClientShipments\s*\(/);
  assert.match(screen, /setItems\(result\.shipments\)|setItems\(\(prev\)/);
  assert.match(types, /ClientShipmentListMeta/);
});

check('structural hierarchy + testIDs', () => {
  assert.match(screen, /testID="shipment-screen"/);
  assert.match(screen, /testID="shipment-list"/);
  assert.match(screen, /testID="shipment-refresh"/);
  assert.match(screen, /testID="shipment-loading"/);
  assert.match(screen, /shipment-empty/);
  assert.match(screen, /testID="shipment-error"/);
  assert.match(screen, /testID="shipment-retry"/);
  assert.match(screen, /Shipments/);
});

check('loading / empty / error+retry exist', () => {
  assert.match(screen, /Loading shipments/);
  assert.match(screen, /No shipments yet/);
  assert.match(screen, /Could not load shipments|Access unavailable/);
  assert.match(screen, /shipment-retry/);
});

check('pull-to-refresh preserves list path', () => {
  assert.match(screen, /RefreshControl/);
  assert.match(screen, /mode === 'refresh'/);
  assert.match(screen, /Preserve existing rows on refresh/);
  assert.match(screen, /setRefreshing\(true\)/);
});

check('pagination uses existing meta contract safely', () => {
  assert.match(types, /lastPage/);
  assert.match(types, /currentPage/);
  assert.match(screen, /mode === 'append'/);
  assert.match(screen, /inFlightRef/);
  assert.match(screen, /shipment-loading-more/);
  assert.match(api, /last_page/);
});

check('UnavailableDomain for unlinked clients intact', () => {
  assert.match(screen, /UnavailableDomainScreen/);
  assert.match(screen, /testID="shipments-unlinked"/);
  assert.match(screen, /identityLinked/);
});

check('Messaging files not modified for this phase (no client shipments coupling)', () => {
  assert.equal(messagingApi.includes('/client/shipments'), false);
  for (const rel of messagingFiles) {
    const body = read(rel);
    assert.equal(body.includes('listClientShipments'), false, rel);
  }
});

check('search/status filters use approved client list API only (C2.6-C5-D7-B / C3.11)', () => {
  // D7-B + C3.11: exact Client lifecycle status names via shared catalog — no ops/tenant params.
  assert.match(screen, /shipment-search-input/);
  assert.match(screen, /EXACT_STATUS_FILTERS|CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.match(screen, /CLIENT_SHIPMENT_STATUS_FILTERS/);
  assert.match(screen, /\.\.\.\(search \? \{ search \} : \{\}\)/);
  assert.match(screen, /\.\.\.\(status \? \{ status \} : \{\}\)/);
  assert.equal(screen.includes('customer_company_id'), false);
  assert.equal(screen.includes('/shipments/queue'), false);
  assert.equal(screen.includes('status_id:'), false);
  assert.equal(screen.includes("'pending'"), false);
  assert.equal(screen.includes("'in_transit'"), false);
  for (const bucket of ['Active', 'Delayed']) {
    assert.equal(screen.includes(`'${bucket}'`), false, `invented bucket literal ${bucket}`);
    assert.equal(screen.includes(`"${bucket}"`), false, `invented bucket literal ${bucket}`);
  }
});

const srcFiles = walk(path.join(root, 'src'));
check('src does not call ops /shipments list', () => {
  for (const f of srcFiles) {
    const rel = path.relative(root, f).replace(/\\/g, '/');
    if (rel.includes('product-source')) continue;
    const body = fs.readFileSync(f, 'utf8');
    if (/apiClient\.(get|post).*['"`]\/shipments['"`]/.test(body)) {
      assert.fail(`${rel} calls ops /shipments`);
    }
  }
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-A structure verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-A structure verification OK — ${passed} checks passed.`);
}
