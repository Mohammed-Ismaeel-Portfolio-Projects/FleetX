/**
 * C2.9-B3.4 Static Verification — Shipment Order Conversion API
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

test('B3.4 implementation doc exists', () => {
  const doc = read('docs/C2.9B3_4_SHIPMENT_ORDER_CONVERSION_API_IMPLEMENTATION.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B3\.4/i);
});

test('convert route registered with correct name', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /\/shipment-requests\/\{shipmentOrder\}\/convert/);
  assert.match(routes, /v1\.shipment-requests\.convert/);
  assert.match(routes, /ShipmentRequestController::class, 'convert'/);
});

test('controller convert method delegates to action', () => {
  const controller = read('backend/app/Http/Controllers/ShipmentRequestController.php');
  assert.match(controller, /function convert/);
  assert.match(controller, /ConvertShipmentOrderToShipmentAction/);
  assert.match(controller, /authorize\('convert'/);
  assert.match(controller, /GetShipmentRequestAction/);
  assert.match(controller, /ShipmentRequestShowResource/);
  assert.match(controller, /201/);
  assert.equal(controller.includes('TransitionShipmentOrderStatusAction'), false);
});

test('ConvertShipmentOrderRequest exists with prohibited fields', () => {
  const request = read('backend/app/Http/Requests/ShipmentOrder/ConvertShipmentOrderRequest.php');
  const lifecycle = read('backend/app/Http/Requests/ShipmentOrder/ProhibitsOpsShipmentOrderLifecycleInput.php');
  assert.ok(request);
  assert.match(request, /ProhibitsOpsShipmentOrderLifecycleInput/);
  assert.match(request, /shipment_order_id.*prohibited/s);
  assert.match(request, /shipment_number.*prohibited/s);
  assert.match(request, /cargo_type.*prohibited/s);
  assert.match(lifecycle, /tracking.*prohibited/s);
  assert.match(lifecycle, /pricing.*prohibited/s);
});

test('shipmentOrder.convert gate and policy remain wired', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /Gate::define\('shipmentOrder\.convert'/);
  const policy = read('backend/app/Policies/ShipmentOrderPolicy.php');
  assert.match(policy, /function convert/);
});

test('B3.4 conversion API tests exist', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/DispatcherShipmentRequestConversionTest.php');
  assert.ok(testFile);
  assert.match(testFile, /\/convert/);
  assert.match(testFile, /assertCreated/);
  assert.match(testFile, /already converted/i);
  assert.match(testFile, /403/);
  assert.match(testFile, /401/);
});

test('ShipmentRequestShowResource exposes linked shipment conversion fields', () => {
  const resource = read('backend/app/Http/Resources/ShipmentOrder/ShipmentRequestShowResource.php');
  assert.match(resource, /converted_shipment/);
  assert.match(resource, /shipment_order_id/);
  assert.match(resource, /convertedShipment->status->name/);
});

test('B3.3 core action unchanged as conversion source of truth', () => {
  const action = read('backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php');
  assert.ok(action);
  assert.match(action, /ShipmentOrderConversionMapper/);
});

test('no B3.5 UI or notification work introduced', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Notifications/ShipmentOrderConvertedNotification.php')),
    false
  );
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-requests/convert')),
    false
  );
});
