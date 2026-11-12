/**
 * C2.6-C5-D5 Shipment Card → Details Navigation — focused verification.
 * Run: node ./scripts/verify-c26c5d5-shipment-card-navigation.mjs
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
const details = read('src/app/shipment-details/[id].tsx');
const layout = read('src/app/_layout.tsx');
const handoff = read('src/app/shipment/[id].tsx');

const cardStart = screen.indexOf('function ShipmentCard');
const screenStart = screen.indexOf('export default function ShipmentsScreen');
assert.ok(cardStart >= 0 && screenStart > cardStart);
const cardBlock = screen.slice(cardStart, screenStart);

check('shipment cards are interactive', () => {
  assert.match(cardBlock, /Pressable/);
  assert.match(cardBlock, /onPress=\{openDetails\}/);
  assert.match(cardBlock, /accessibilityRole="button"/);
});

check('navigation targets /shipment-details/[id]', () => {
  assert.match(cardBlock, /router\.push\(`\/shipment-details\/\$\{item\.id\}`\)/);
  assert.match(layout, /shipment-details\/\[id\]/);
  assert.match(details, /getClientShipment/);
});

check('navigation uses shipment id', () => {
  assert.match(cardBlock, /item\.id/);
  assert.match(cardBlock, /if \(!item\.id\) return/);
});

check('legacy /shipment-details without id is not restored', () => {
  assert.equal(fs.existsSync(path.join(root, 'src/app/shipment-details.tsx')), false);
  assert.equal(cardBlock.includes("'/shipment-details'"), false);
  assert.equal(cardBlock.includes('"/shipment-details"'), false);
  assert.equal(/router\.push\(\s*['"`]\/shipment-details['"`]\s*\)/.test(screen), false);
});

check('/shipment/[id] handoff is not used for card navigation', () => {
  assert.equal(cardBlock.includes('/shipment/${'), false);
  assert.equal(cardBlock.includes('`/shipment/'), false);
  assert.equal(/router\.push\(`\/shipment\/\$\{/.test(cardBlock), false);
  assert.match(handoff, /findAuthorizedConversationForShipment/);
});

check('card does not call details API directly', () => {
  assert.equal(cardBlock.includes('getClientShipment'), false);
  assert.equal(cardBlock.includes('/client/shipments/'), false);
  assert.equal(screen.includes('listClientShipments'), true);
});

check('no company/tenant parameters added', () => {
  assert.equal(cardBlock.includes('customer_company_id'), false);
  assert.equal(cardBlock.includes('company_id'), false);
  assert.equal(cardBlock.includes('driver_id'), false);
  assert.equal(cardBlock.includes('dispatcher_id'), false);
});

check('ops /shipments/{id} is not referenced', () => {
  assert.equal(screen.includes("'/shipments/"), false);
  assert.equal(screen.includes('"/shipments/'), false);
  assert.equal(api.includes("'/shipments/"), false);
});

check('forbidden-data boundary remains intact', () => {
  for (const token of [
    'driverPhone',
    'pickupAddress',
    'deliveryAddress',
    'specialInstructions',
    'item.notes',
    'Track',
    'Edit',
    'Contact dispatcher',
    'Message driver',
  ]) {
    assert.equal(cardBlock.includes(token), false, token);
  }
});

check('list still uses client list endpoint only', () => {
  assert.match(api, /listClientShipments/);
  assert.match(screen, /listClientShipments/);
  assert.match(api, /\/client\/shipments/);
});

if (process.exitCode) {
  console.error(`\nC2.6-C5-D5 card navigation verification FAILED (${passed} checks passed before failure).`);
} else {
  console.log(`\nC2.6-C5-D5 card navigation verification OK — ${passed} checks passed.`);
}
