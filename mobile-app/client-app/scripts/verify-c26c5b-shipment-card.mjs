/**
 * C2.6-C5-B Client Shipment Card — focused verification.
 * Run: node ./scripts/verify-c26c5b-shipment-card.mjs
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

check('approved shipment fields are used', () => {
  assert.match(screen, /item\.shipmentNumber/);
  assert.match(screen, /item\.status\?\.name/);
  assert.match(screen, /item\.pickupCity|pickupCity/);
  assert.match(screen, /item\.deliveryCity|deliveryCity/);
  assert.match(screen, /plannedPickupDate/);
  assert.match(screen, /plannedDeliveryDate/);
  assert.match(screen, /actualPickupDate/);
  assert.match(screen, /actualDeliveryDate/);
  assert.match(screen, /item\.priority/);
  assert.match(types, /shipmentNumber/);
  assert.match(types, /customerCompany/);
});

check('no forbidden PII / driver / dispatcher fields', () => {
  for (const token of [
    'driverPhone',
    'driver_phone',
    'driverName',
    'driver_name',
    'dispatcher',
    'pickupAddress',
    'deliveryAddress',
    'specialInstructions',
    'special_instructions',
    'item.notes',
    'driver_id',
    'dispatcher_id',
    'latitude',
    'longitude',
    'LIVE ETA',
  ]) {
    assert.equal(screen.includes(token), false, token);
  }
  assert.equal(types.includes('driverPhone'), false);
  assert.equal(types.includes('pickupAddress'), false);
});

check('no fake shipment IDs / GPS / progress mocks', () => {
  for (const token of [
    'FX-4920',
    'FX-1620',
    'Robert Chen',
    'SafeMap',
    'LIVE ETA',
    'progress bar',
    'ProgressBar',
    'mockShipments',
  ]) {
    assert.equal(screen.includes(token), false, token);
  }
});

check('customer-safe status projection; no visible raw status (C3.10/C3.12)', () => {
  assert.match(screen, /item\.status\?\.name/);
  assert.match(screen, /customerFacingShipmentStatus\(statusRaw\)/);
  assert.equal(screen.includes('shipment-status-raw-'), false);
  assert.equal(api.includes('function formatStatusLabel'), false);
  assert.equal(screen.includes("status: 'In Transit'"), false);
  assert.equal(api.includes('query.status = formatStatusLabel'), false);
});

check('card hierarchy + structural testIDs', () => {
  assert.match(screen, /shipment-card-\$/);
  assert.match(screen, /shipment-number-\$/);
  assert.match(screen, /shipment-status-\$/);
  assert.match(screen, /shipment-route-\$/);
  assert.match(screen, /shipment-dates-\$/);
  assert.match(screen, /shipment-priority-\$/);
  assert.match(screen, /Planned/);
  assert.match(screen, /Actual/);
});

check('card navigates to client details only — no handoff/track/edit', () => {
  assert.match(screen, /router\.push\(`\/shipment-details\/\$\{item\.id\}`\)/);
  assert.equal(screen.includes("'/tracking'"), false);
  assert.equal(screen.includes('Create Shipment'), false);
  assert.equal(screen.includes('Contact dispatcher'), false);
  assert.equal(/router\.push\(`\/shipment\/\$\{/.test(screen), false);
  assert.equal(fs.existsSync(path.join(root, 'src/app/shipment-details.tsx')), false);
  const cardStart = screen.indexOf('function ShipmentCard');
  const screenStart = screen.indexOf('export default function ShipmentsScreen');
  assert.ok(cardStart >= 0 && screenStart > cardStart, 'ShipmentCard bounds');
  const cardBlock = screen.slice(cardStart, screenStart);
  assert.match(cardBlock, /Pressable/);
  assert.match(cardBlock, /onPress/);
  assert.equal(cardBlock.includes('getClientShipment'), false);
});

check('client endpoint unchanged; no ops endpoints', () => {
  assert.match(api, /\/client\/shipments/);
  assert.equal(api.includes("'/shipments'"), false);
  assert.equal(api.includes('/shipments/queue'), false);
  assert.equal(api.includes('/shipments/kpis'), false);
  assert.match(screen, /listClientShipments/);
});

check('no company/driver scope params from card/list screen', () => {
  assert.equal(screen.includes('customer_company_id'), false);
  assert.equal(screen.includes('company_id:'), false);
  assert.match(api, /customer_company_id/);
  assert.match(api, /delete \(query/);
});

check('UnavailableDomain behavior intact', () => {
  assert.match(screen, /UnavailableDomainScreen/);
  assert.match(screen, /shipments-unlinked/);
  assert.match(screen, /identityLinked/);
});

check('Messaging untouched by card phase', () => {
  assert.equal(messagingApi.includes('/client/shipments'), false);
  assert.equal(messagingApi.includes('listClientShipments'), false);
});

check('optional null handling helpers present', () => {
  assert.match(screen, /formatDisplayDate/);
  assert.match(screen, /formatPriorityLabel/);
  assert.match(screen, /formatPlaceSummary/);
  assert.equal(screen.includes('"undefined"'), false);
  assert.equal(screen.includes("'null'"), false);
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-B shipment card verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-B shipment card verification OK — ${passed} checks passed.`);
}
