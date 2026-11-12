/**
 * C2.9-B3.2 Static Verification — Shipment Conversion Persistence Prerequisites
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

test('B3.2 implementation doc exists', () => {
  const doc = read('docs/C2.9B3_2_SHIPMENT_CONVERSION_PERSISTENCE_PREREQUISITES.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B3\.2/i);
});

test('shipment_order_id migration exists', () => {
  const mig = read('backend/database/migrations/2026_08_12_120000_add_shipment_order_id_to_shipments_table.php');
  assert.ok(mig);
  assert.match(mig, /shipment_order_id/);
  assert.match(mig, /uuid\('shipment_order_id'\)/);
  assert.match(mig, /unique\(\)/);
  assert.match(mig, /shipment_orders/);
  assert.match(mig, /restrictOnDelete/);
});

test('Shipment model has reverse relationship', () => {
  const model = read('backend/app/Models/Shipment.php');
  assert.ok(model);
  assert.match(model, /shipment_order_id/);
  assert.match(model, /function shipmentOrder/);
  assert.match(model, /belongsTo\(ShipmentOrder::class/);
});

test('ShipmentOrder model has inverse relationship', () => {
  const model = read('backend/app/Models/ShipmentOrder.php');
  assert.ok(model);
  assert.match(model, /function linkedShipment/);
  assert.match(model, /hasOne\(Shipment::class, 'shipment_order_id'\)/);
});

test('ShipmentStatus canonical class exists with Waiting Assignment', () => {
  const status = read('backend/app/Support/ShipmentStatus.php');
  assert.ok(status);
  assert.match(status, /TYPE = 'shipment'/);
  assert.match(status, /WAITING_ASSIGNMENT = 'Waiting Assignment'/);
  assert.match(status, /CONVERSION_INITIAL/);
  assert.match(status, /conversionInitialStatusId/);
});

test('ShipmentStatusesSeeder exists and is idempotent', () => {
  const seeder = read('backend/database/seeders/ShipmentStatusesSeeder.php');
  assert.ok(seeder);
  assert.match(seeder, /ShipmentStatus::all/);
  assert.match(seeder, /updateOrCreate/);
});

test('B3.2 persistence tests exist', () => {
  const testFile = read('backend/tests/Unit/Models/ShipmentConversionPersistenceTest.php');
  assert.ok(testFile);
  assert.match(testFile, /two_shipments_cannot_reference_same_shipment_order/);
  assert.match(testFile, /waiting_assignment_exists_for_shipment_type/);
  assert.match(testFile, /conversion_initial_status_is_not_shipment_order_domain/);
});

test('B3.3 conversion core implemented (action + gate)', () => {
  const action = read('backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php');
  assert.ok(action, 'ConvertShipmentOrderToShipmentAction expected after B3.3');
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  assert.match(provider, /shipmentOrder\.convert/);
});

test('convert HTTP route implemented in B3.4', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /\/convert/);
  assert.match(routes, /v1\.shipment-requests\.convert/);
});

test('forward converted_shipment_id relationship unchanged', () => {
  const order = read('backend/app/Models/ShipmentOrder.php');
  assert.match(order, /converted_shipment_id/);
  assert.match(order, /convertedShipment/);
});

test('existing conversion forward FK migration unchanged', () => {
  const mig = read('backend/database/migrations/2026_08_12_100000_create_shipment_orders_table.php');
  assert.match(mig, /converted_shipment_id/);
  assert.match(mig, /unique\(\)/);
});
