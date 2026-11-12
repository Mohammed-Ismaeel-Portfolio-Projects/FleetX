/**
 * C2.9-B3.3 Static Verification — Shipment Order Conversion Core
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

test('B3.3 implementation doc exists', () => {
  const doc = read('docs/C2.9B3_3_SHIPMENT_ORDER_CONVERSION_CORE_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B3\.3/i);
  assert.match(doc, /B3\.4.*deferred|deferred.*B3\.4/i);
});

test('ConvertShipmentOrderToShipmentAction exists', () => {
  const action = read('backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php');
  assert.ok(action);
  assert.match(action, /lockForUpdate/);
  assert.match(action, /TransitionShipmentOrderStatusAction/);
  assert.match(action, /ShipmentOrderConversionMapper/);
  assert.match(action, /CreateShipmentAction/);
  assert.match(action, /converted_shipment_id/);
  assert.match(action, /shipment_order_id/);
  assert.match(action, /ShipmentOrderStatus::CONVERTED/);
  assert.match(action, /isAlreadyConverted/);
});

test('ShipmentOrderConversionMapper exists', () => {
  const mapper = read('backend/app/Actions/ShipmentOrder/ShipmentOrderConversionMapper.php');
  assert.ok(mapper);
  assert.match(mapper, /ShipmentStatus::conversionInitialStatusId/);
  assert.match(mapper, /planned_pickup_date.*requested_pickup_date/s);
  assert.match(mapper, /planned_delivery_date.*requested_delivery_date/s);
  assert.match(mapper, /priority.*normal/s);
  assert.match(mapper, /mapNotes/);
  assert.equal(mapper.includes('payload_json'), false);
  assert.equal(mapper.includes('submission_revision'), false);
  assert.equal(mapper.includes('submitted_by_user_id'), false);
});

test('shipmentOrder.convert gate and policy wired', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('shipmentOrder\.convert'/);
  assert.match(provider, /admin.*dispatcher|dispatcher.*admin/s);

  const policy = read('backend/app/Policies/ShipmentOrderPolicy.php');
  assert.match(policy, /function convert/);
  assert.match(policy, /shipmentOrder\.convert/);
});

test('canonical Shipment conversion status used', () => {
  const status = read('backend/app/Support/ShipmentStatus.php');
  assert.match(status, /CONVERSION_INITIAL = self::WAITING_ASSIGNMENT/);
  const mapper = read('backend/app/Actions/ShipmentOrder/ShipmentOrderConversionMapper.php');
  assert.match(mapper, /conversionInitialStatusId/);
});

test('B3.3 conversion tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ConvertShipmentOrderToShipmentActionTest.php');
  assert.ok(testFile);
  assert.match(testFile, /shipmentOrder\.convert/);
  assert.match(testFile, /idempotent/i);
  assert.match(testFile, /concurrent/i);
  assert.match(testFile, /ShipmentOrderConversionMapper/);
});

test('convert HTTP route deferred to B3.4 (core remains domain-only in B3.3)', () => {
  const routes = read('backend/routes/api.php') || '';
  const b34Doc = read('docs/C2.9B3_4_SHIPMENT_ORDER_CONVERSION_API_IMPLEMENTATION.md');
  if (b34Doc) {
    assert.match(routes, /\/convert/);
    assert.match(routes, /v1\.shipment-requests\.convert/);
    return;
  }
  assert.equal(routes.includes('/convert'), false);
  const controller = read('backend/app/Http/Controllers/ShipmentRequestController.php') || '';
  assert.equal(/function convert/i.test(controller), false);
});

test('no B3.4 UI or notification work introduced', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Notifications/ShipmentOrderConvertedNotification.php')),
    false
  );
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-requests/convert')),
    false
  );
});
