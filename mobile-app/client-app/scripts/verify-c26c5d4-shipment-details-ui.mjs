/**
 * C2.6-C5-D4.1 Client Shipment Details UI — focused verification.
 * Run: node ./scripts/verify-c26c5d4-shipment-details-ui.mjs
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

const details = read('src/app/shipment-details/[id].tsx');
const api = read('src/features/shipments/shipmentsApi.ts');
const types = read('src/features/shipments/types.ts');
const list = read('src/app/(tabs)/shipments.tsx');
const handoff = read('src/app/shipment/[id].tsx');
const messagingApi = read('src/features/messaging/messagingApi.ts');
const layout = read('src/app/_layout.tsx');

check('dedicated client show endpoint', () => {
  assert.match(api, /function getClientShipment/);
  assert.match(api, /\/client\/shipments\/\$\{/);
  assert.match(details, /getClientShipment/);
});

check('no ops show endpoint', () => {
  assert.equal(api.includes("'/shipments/'"), false);
  assert.equal(/get\([^)]*['"`]\/shipments\//.test(api), false);
  assert.equal(details.includes('/api/v1/shipments'), false);
  assert.equal(details.includes("'/shipments/"), false);
});

check('no tenancy parameters', () => {
  assert.equal(details.includes('customer_company_id'), false);
  assert.equal(details.includes('company_id:'), false);
  assert.equal(details.includes('driver_id'), false);
  const start = api.indexOf('export async function getClientShipment(');
  const end = api.indexOf('export async function getClientShipmentTracking');
  assert.ok(start >= 0, 'getClientShipment present');
  const showFn = end > start ? api.slice(start, end) : api.slice(start);
  assert.equal(showFn.includes('params:'), false);
  assert.equal(/params:[\s\S]*customer_company_id/.test(showFn), false);
  assert.equal(/query\.customer_company_id|query\.company_id/.test(showFn), false);
});

check('approved field usage only', () => {
  assert.match(details, /shipmentNumber|shipment\.shipmentNumber/);
  assert.match(details, /status\?\.name|statusRaw/);
  assert.match(details, /formatCityCountry/);
  assert.match(details, /plannedPickupDate/);
  assert.match(details, /cargoType|cargoDescription|packageCount/);
  assert.match(types, /ClientShipmentDetails/);
  assert.match(types, /cargoType/);
});

check('forbidden PII absence', () => {
  for (const token of [
    'driverPhone',
    'driver_phone',
    'pickupAddress',
    'deliveryAddress',
    'pickup_phone',
    'specialInstructions',
    'special_instructions',
    'item.notes',
    'trackingLogs',
    'activeAssignment',
    'latitude',
    'longitude',
  ]) {
    assert.equal(details.includes(token), false, token);
  }
});

check('loading / success / error states', () => {
  assert.match(details, /shipment-details-loading/);
  assert.match(details, /shipment-details-screen/);
  assert.match(details, /shipment-details-error/);
  assert.match(details, /shipment-details-retry/);
});

check('401 / 403 / 404 handling', () => {
  assert.match(details, /statusCode === 401/);
  assert.match(details, /do not reinterpret as empty details/);
  assert.match(details, /shipment-details-forbidden/);
  assert.match(details, /shipment-details-not-found/);
  assert.match(details, /errorStatus === 403/);
  assert.match(details, /errorStatus === 404/);
});

check('no mock shipment data', () => {
  for (const token of ['FX-4920', 'FX-1620', 'Robert Chen', 'SafeMap', 'LIVE ETA', 'mockShipment']) {
    assert.equal(details.includes(token), false, token);
  }
});

check('messaging untouched / handoff distinct', () => {
  assert.equal(messagingApi.includes('getClientShipment'), false);
  assert.equal(messagingApi.includes('/client/shipments/'), false);
  assert.match(handoff, /findAuthorizedConversationForShipment/);
  assert.equal(handoff.includes('getClientShipment'), false);
  assert.match(details, /Distinct from protected \/shipment\/\[id\]/);
});

check('cards navigate to details without fetching details in list', () => {
  assert.match(list, /\/shipment-details\/\$\{item\.id\}/);
  assert.equal(list.includes('getClientShipment'), false);
  assert.equal(/router\.push\(`\/shipment\/\$\{/.test(list), false);
  const cardStart = list.indexOf('function ShipmentCard');
  const screenStart = list.indexOf('export default function ShipmentsScreen');
  const cardBlock = list.slice(cardStart, screenStart);
  assert.match(cardBlock, /Pressable/);
  assert.match(cardBlock, /onPress/);
});

check('no Track / Edit / Create actions on details', () => {
  assert.equal(/\bTrack\b/.test(details), false);
  assert.equal(details.includes('Edit'), false);
  assert.equal(details.includes('Create Shipment'), false);
  assert.equal(details.includes('Contact dispatcher'), false);
  assert.equal(details.includes('Message driver'), false);
  assert.match(details, /Shipment progress/);
});

check('stack registers shipment-details/[id]', () => {
  assert.match(layout, /shipment-details\/\[id\]/);
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-D4.1 details UI verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-D4.1 details UI verification OK — ${passed} checks passed.`);
}
