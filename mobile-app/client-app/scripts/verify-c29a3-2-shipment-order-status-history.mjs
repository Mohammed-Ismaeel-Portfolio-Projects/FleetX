/**
 * C2.9-A3.2 Static Verification — Shipment Order Status History & Transitions
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

test('status history migration exists', () => {
  const mig = read('backend/database/migrations/2026_08_12_110000_create_shipment_order_status_history_table.php');
  assert.ok(mig);
  assert.match(mig, /shipment_order_status_history/);
});

test('ShipmentOrderStatusHistory model exists', () => {
  const model = read('backend/app/Models/ShipmentOrderStatusHistory.php');
  assert.ok(model);
  assert.match(model, /class ShipmentOrderStatusHistory/);
});

test('TransitionShipmentOrderStatusAction exists', () => {
  const action = read('backend/app/Actions/ShipmentOrder/TransitionShipmentOrderStatusAction.php');
  assert.ok(action);
  assert.match(action, /class TransitionShipmentOrderStatusAction/);
  assert.match(action, /DB::transaction/);
  assert.match(action, /lockForUpdate/);
});

test('ShipmentOrderTransitionMap contains required states', () => {
  const map = read('backend/app/Support/ShipmentOrderTransitionMap.php');
  assert.ok(map);
  assert.match(map, /ShipmentOrderStatus::DRAFT/);
  assert.match(map, /ShipmentOrderStatus::SUBMITTED/);
  assert.match(map, /UNDER_REVIEW/);
  assert.match(map, /CHANGES_REQUESTED/);
  assert.match(map, /APPROVED/);
  assert.match(map, /REJECTED/);
  assert.match(map, /CANCELLED/);
  assert.match(map, /CONVERTED/);
});

test('terminal states are protected in transition map', () => {
  const map = read('backend/app/Support/ShipmentOrderTransitionMap.php');
  assert.match(map, /isTerminal/);
  assert.match(map, /REJECTED/);
  assert.match(map, /CANCELLED/);
  assert.match(map, /CONVERTED/);
});

test('ShipmentOrder has statusHistory relationship', () => {
  const model = read('backend/app/Models/ShipmentOrder.php');
  assert.match(model, /statusHistory/);
  assert.match(model, /latestStatusHistory/);
  assert.match(model, /TransitionShipmentOrderStatusAction/);
});

test('transition tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/TransitionShipmentOrderStatusTest.php');
  assert.ok(testFile);
  assert.match(testFile, /TransitionShipmentOrderStatusTest/);
  assert.match(testFile, /InvalidShipmentOrderTransitionException/);
});

test('documentation exists', () => {
  const doc = read('docs/C2.9A3_2_SHIPMENT_ORDER_STATUS_HISTORY_AND_TRANSITIONS.md');
  assert.ok(doc);
  assert.match(doc, /Transition Matrix/i);
  assert.match(doc, /NOT IMPLEMENTED/i);
});

test('no Client shipment-order API introduced', () => {
  const routes = read('backend/routes/api.php');
  assert.equal(routes.includes('ShipmentOrderController'), false);
  assert.equal(routes.includes('shipment-orders'), false);
});

test('no Client UI shipment-order implementation', () => {
  const index = read('mobile-app/client-app/src/app/(tabs)/index.tsx') || '';
  assert.equal(/listClientShipmentOrders|shipment-order/i.test(index) && /shipmentsApi/i.test(index), false);
});

test('no conversion action introduced', () => {
  assert.equal(existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php')), false);
});

test('shipment operational status API not modified', () => {
  const action = read('backend/app/Actions/Shipment/ChangeShipmentStatusAction.php');
  assert.ok(action);
  assert.equal(action.includes('ShipmentOrder'), false);
});

test('scope limited to status history and transitions', () => {
  const doc = read('docs/C2.9A3_2_SHIPMENT_ORDER_STATUS_HISTORY_AND_TRANSITIONS.md');
  assert.match(doc, /no API/i);
  assert.match(doc, /C2\.9-A3\.3/);
});
