/**
 * C2.9-A3.1 Static Verification — Shipment Order Persistence Foundation
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..', '..');

function read(rel) {
  const p = resolve(ROOT, rel);
  if (!existsSync(p)) return null;
  return readFileSync(p, 'utf8');
}

test('ShipmentOrder migration exists', () => {
  const mig = read('backend/database/migrations/2026_08_12_100000_create_shipment_orders_table.php');
  assert.ok(mig);
  assert.match(mig, /create\('shipment_orders'/);
});

test('ShipmentOrder model exists', () => {
  const model = read('backend/app/Models/ShipmentOrder.php');
  assert.ok(model);
  assert.match(model, /class ShipmentOrder extends Model/);
});

test('canonical table name shipment_orders', () => {
  const model = read('backend/app/Models/ShipmentOrder.php');
  assert.match(model, /ShipmentOrder/);
  const mig = read('backend/database/migrations/2026_08_12_100000_create_shipment_orders_table.php');
  assert.match(mig, /shipment_orders/);
});

test('canonical status vocabulary exists', () => {
  const vocab = read('backend/app/Support/ShipmentOrderStatus.php');
  assert.ok(vocab);
  assert.match(vocab, /shipment_order/);
  assert.match(vocab, /draft/);
  assert.match(vocab, /submitted/);
  assert.match(vocab, /under_review/);
  assert.match(vocab, /changes_requested/);
  assert.match(vocab, /approved/);
  assert.match(vocab, /rejected/);
  assert.match(vocab, /cancelled/);
  assert.match(vocab, /converted/);
});

test('customer_company_id exists in schema', () => {
  const mig = read('backend/database/migrations/2026_08_12_100000_create_shipment_orders_table.php');
  assert.match(mig, /customer_company_id/);
  const model = read('backend/app/Models/ShipmentOrder.php');
  assert.match(model, /scopeForCustomer/i);
});

test('submitting user ownership exists', () => {
  const mig = read('backend/database/migrations/2026_08_12_100000_create_shipment_orders_table.php');
  assert.match(mig, /submitted_by_user_id/);
  const model = read('backend/app/Models/ShipmentOrder.php');
  assert.match(model, /submittedBy/);
});

test('submission_revision and payload_json exist', () => {
  const mig = read('backend/database/migrations/2026_08_12_100000_create_shipment_orders_table.php');
  assert.match(mig, /submission_revision/);
  assert.match(mig, /payload_json/);
});

test('converted_shipment_id with unique constraint', () => {
  const mig = read('backend/database/migrations/2026_08_12_100000_create_shipment_orders_table.php');
  assert.match(mig, /converted_shipment_id/);
  assert.match(mig, /unique\(\)/);
});

test('shipments.shipment_order_id deferred — documented', () => {
  const doc = read('docs/C2.9A3_1_SHIPMENT_ORDER_PERSISTENCE_FOUNDATION.md');
  assert.ok(doc);
  assert.match(doc, /shipment_order_id.*DEFERRED|DEFERRED.*shipment_order_id/i);
});

test('no shipment order API routes introduced', () => {
  const routes = read('backend/routes/api.php');
  assert.ok(routes);
  assert.equal(routes.includes('ShipmentOrderController'), false);
  assert.equal(routes.includes('shipment-orders'), false);
  assert.equal(routes.includes('shipment_orders'), false);
});

test('no conversion action introduced', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php')),
    false
  );
});

test('no client shipment-order UI introduced', () => {
  const appSrc = read('mobile-app/client-app/src/app/(tabs)/index.tsx') || '';
  assert.equal(/shipment-order|shipmentOrder/i.test(appSrc) && /listClientShipmentOrders/i.test(appSrc), false);
});

test('persistence tests exist', () => {
  const test = read('backend/tests/Unit/Models/ShipmentOrderPersistenceTest.php');
  assert.ok(test);
  assert.match(test, /ShipmentOrderPersistenceTest/);
});

test('implementation doc exists and scope is limited', () => {
  const doc = read('docs/C2.9A3_1_SHIPMENT_ORDER_PERSISTENCE_FOUNDATION.md');
  assert.ok(doc);
  assert.match(doc, /Persistence Foundation/i);
  assert.match(doc, /NOT IMPLEMENTED|NON-GOAL|Out of scope/i);
  assert.match(doc, /C2\.9-A3\.2|A3\.2/i);
});

test('no transition methods on model', () => {
  const model = read('backend/app/Models/ShipmentOrder.php');
  assert.equal(/function (approve|reject|convert|submit)\(/i.test(model), false);
});

test('shipment operational status not replaced', () => {
  const shipment = read('backend/app/Models/Shipment.php');
  assert.match(shipment, /status_id/);
  assert.equal(shipment.includes('ShipmentOrderStatus'), false);
});
