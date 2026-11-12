/**
 * C2.6 Client Shipment List — boundary verification (updated after C2.6-C2/C4).
 * Ensures Client App uses only GET /client/shipments and never ops endpoints.
 *
 * Run: node ./scripts/verify-c26-shipment-list.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const repoRoot = path.join(root, '../..');

function read(rel, base = root) {
  return fs.readFileSync(path.join(base, rel), 'utf8');
}

function walkTsFiles(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const name of fs.readdirSync(dir)) {
    if (name === 'product-source' || name === 'node_modules') continue;
    const full = path.join(dir, name);
    const st = fs.statSync(full);
    if (st.isDirectory()) walkTsFiles(full, out);
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

const auditPath = path.join(repoRoot, 'docs/C2.6_CLIENT_SHIPMENT_LIST_CONTRACT_AUDIT.md');
const implPath = path.join(repoRoot, 'docs/C2.6C2_CLIENT_SHIPMENT_LIST_IMPLEMENTATION.md');
const shipmentsTab = read('src/app/(tabs)/shipments.tsx');
const home = read('src/app/(tabs)/index.tsx');
const api = read('src/features/shipments/shipmentsApi.ts');
const srcFiles = walkTsFiles(path.join(root, 'src'));
const srcBlob = srcFiles.map((f) => fs.readFileSync(f, 'utf8')).join('\n');

check('audit document exists', () => {
  assert.ok(fs.existsSync(auditPath));
});

check('C2.6-C2 implementation doc exists', () => {
  assert.ok(fs.existsSync(implPath));
});

check('Shipments tab uses client list API', () => {
  assert.match(shipmentsTab, /listClientShipments/);
  assert.match(api, /\/client\/shipments/);
});

check('no invented alternate client shipment endpoints', () => {
  assert.equal(srcBlob.includes('/customer/shipments'), false);
  assert.equal(srcBlob.includes('/my/shipments'), false);
});

check('Client App does not consume ops shipment list/queue/kpis APIs', () => {
  const apiCallPatterns = [
    /['"`]\/shipments['"`]/,
    /['"`]\/shipments\?/,
    /['"`]\/shipments\/queue['"`]/,
    /['"`]\/shipments\/kpis['"`]/,
    /apiClient\.(get|post|put|patch|delete)\(\s*['"`]\/shipments/,
  ];
  for (const re of apiCallPatterns) {
    assert.equal(re.test(srcBlob), false, `Forbidden pattern: ${re}`);
  }
});

check('no mock shipment IDs in app routes', () => {
  const appRoutes = [
    'src/app/(tabs)/shipments.tsx',
    'src/app/(tabs)/index.tsx',
    'src/app/(tabs)/tracking.tsx',
    'src/app/notifications.tsx',
    'src/app/shipment-details/[id].tsx',
  ];
  for (const rel of appRoutes) {
    const src = read(rel);
    assert.equal(src.includes('FX-4920'), false, rel);
    assert.equal(src.includes('FX-1620'), false, rel);
    assert.equal(src.includes('FX-3844'), false, rel);
  }
});

check('Home consumes client shipment list for overview (C3.3); Shipments tab remains the full list', () => {
  assert.match(home, /listClientShipments/);
  assert.equal(home.includes('home-shipments-unavailable'), false);
});

check('backend evidence: client.shipment.viewAny gate exists', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php', repoRoot);
  assert.match(provider, /client\.shipment\.viewAny/);
});

check('API module never sends customer_company_id as query tenancy', () => {
  assert.match(api, /\/client\/shipments/);
  assert.match(api, /delete \(query[\s\S]*customer_company_id/);
  assert.match(api, /delete \(query[\s\S]*company_id/);
  assert.equal(api.includes("params: { customer_company_id"), false);
  assert.equal(api.includes("'/shipments'"), false);
});

if (!process.exitCode) {
  console.log(`\nC2.6 shipment list checks: ${passed} passed`);
}
