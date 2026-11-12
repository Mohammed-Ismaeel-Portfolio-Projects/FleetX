/**
 * C2.9-B3.1 Static Verification — Shipment Order Conversion Architecture Audit
 * Audit-only phase: confirms documentation exists and no conversion implementation was introduced.
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

test('audit document exists with required phase id', () => {
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B3\.1/i);
  assert.match(doc, /AUDIT ONLY|audit only/i);
});

test('audit contains required sections', () => {
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  const sections = [
    /Executive Summary/i,
    /Mapping Matrix/i,
    /Status Domain/i,
    /Conversion Lifecycle/i,
    /Transaction/i,
    /Idempotency/i,
    /Traceability/i,
    /Audit Log/i,
    /Authorization/i,
    /Assignment Dependency/i,
    /Failure Matrix/i,
    /Future API/i,
    /Implementation Plan/i,
    /Final Verdict/i,
  ];
  for (const section of sections) {
    assert.match(doc, section);
  }
});

test('audit recommends dedicated ConvertShipmentOrderToShipmentAction', () => {
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /ConvertShipmentOrderToShipmentAction/);
  assert.match(doc, /TransitionShipmentOrderStatusAction/);
  assert.match(doc, /CreateShipmentAction/);
});

test('audit documents approved to converted transition', () => {
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /approved.*converted|converted.*approved/is);
});

test('ConvertShipmentOrderToShipmentAction implemented in B3.3 (audit recommended it)', () => {
  const actionPath = resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php');
  assert.equal(existsSync(actionPath), true);
  const b33Doc = read('docs/C2.9B3_3_SHIPMENT_ORDER_CONVERSION_CORE_IMPLEMENTATION.md');
  assert.ok(b33Doc);
});

test('conversion route added in B3.4 with controller convert method', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /\/convert/);
  assert.match(routes, /v1\.shipment-requests\.convert/);
  const controller = read('backend/app/Http/Controllers/ShipmentRequestController.php') || '';
  assert.match(controller, /function convert/);
});

test('shipmentOrder.convert gate implemented in B3.3 (audit predicted it)', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /shipmentOrder\.convert/);
  assert.match(provider, /shipmentOrder\.convert/);
});

test('shipments.shipment_order_id migration added in B3.2', () => {
  const mig = read('backend/database/migrations/2026_08_12_120000_add_shipment_order_id_to_shipments_table.php');
  assert.ok(mig);
  assert.match(mig, /shipment_order_id/);
  assert.match(mig, /unique\(\)/);
  assert.match(mig, /restrictOnDelete/);
  const b32Doc = read('docs/C2.9B3_2_SHIPMENT_CONVERSION_PERSISTENCE_PREREQUISITES.md');
  assert.ok(b32Doc);
});

test('no Dispatcher UI conversion work introduced', () => {
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx') || '';
  assert.equal(/convert|conversion/i.test(layout), false);
  assert.equal(
    existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-requests/convert')),
    false
  );
});

test('no Client UI conversion work introduced', () => {
  const index = read('mobile-app/client-app/src/app/(tabs)/index.tsx') || '';
  assert.equal(/convert.*shipment|shipment.*convert/i.test(index), false);
});

test('assignment gates still not fixed during audit', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  assert.equal(provider.includes("Gate::define('assignment."), false);
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /assignment.*gate|assignment\.\*/i);
});

test('no conversion notification implementation introduced', () => {
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /notification.*deferred|deferred.*notification|No.*notification/i);
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Notifications/ShipmentOrderConvertedNotification.php')),
    false
  );
});

test('existing review and client routes remain intact', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /v1\.shipment-requests\.approve/);
  assert.match(routes, /v1\.client\.shipment-requests\.resubmit/);
  assert.match(routes, /v1\.shipments\.store/);
});

test('audit certifies hard stop before implementation', () => {
  const doc = read('docs/C2.9B3_1_SHIPMENT_ORDER_CONVERSION_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /HARD STOP|Awaiting explicit approval/i);
});
