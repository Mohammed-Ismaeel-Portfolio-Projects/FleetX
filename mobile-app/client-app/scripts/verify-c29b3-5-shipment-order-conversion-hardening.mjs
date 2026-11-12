/**
 * C2.9-B3.5 Static Verification — Shipment Order Conversion Hardening
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

test('B3.5 hardening doc exists', () => {
  const doc = read('docs/C2.9B3_5_SHIPMENT_ORDER_CONVERSION_HARDENING.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B3\.5/i);
});

test('B3.5 hardening test suite exists', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ShipmentOrderConversionConcurrencyTest.php');
  assert.ok(testFile);
  assert.match(testFile, /ShipmentOrderConversionConcurrencyTest/);
});

test('concurrency coverage exists', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ShipmentOrderConversionConcurrencyTest.php');
  assert.match(testFile, /201.*200|assertCreated|already converted/is);
  assert.match(testFile, /sequential_conversion|triple_convert/i);
});

test('idempotency coverage exists', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ShipmentOrderConversionConcurrencyTest.php');
  assert.match(testFile, /idempotent|triple_convert/i);
});

test('rollback coverage exists', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ShipmentOrderConversionConcurrencyTest.php');
  assert.match(testFile, /rollback|rolls_back|failed_shipment_creation|transition_failure/i);
});

test('invariant coverage exists', () => {
  const testFile = read('backend/tests/Feature/ShipmentOrder/ShipmentOrderConversionConcurrencyTest.php');
  assert.match(testFile, /database_invariants|unique_constraint|linkedShipment/i);
  assert.match(testFile, /approved_to_converted|lifecycle/i);
});

test('B3.4 endpoint remains present', () => {
  const routes = read('backend/routes/api.php');
  assert.match(routes, /v1\.shipment-requests\.convert/);
});

test('B3.3 conversion core remains present', () => {
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php')));
  assert.ok(existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ShipmentOrderConversionMapper.php')));
});

test('dedicated conversion gate remains present', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php');
  assert.match(provider, /shipmentOrder\.convert/);
});

test('no forbidden UI assignment or notification work introduced', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Notifications/ShipmentOrderConvertedNotification.php')),
    false
  );
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-requests/convert')),
    false
  );
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  assert.equal(provider.includes("Gate::define('assignment."), false);
});
