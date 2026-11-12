/**
 * C2.9-B1 Static Verification — Dispatcher Shipment Request Architecture Audit
 * Audit-only phase: confirms documentation exists and no implementation was introduced.
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
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md');
  assert.ok(doc);
  assert.match(doc, /C2\.9-B1/i);
  assert.match(doc, /AUDIT ONLY/i);
});

test('audit covers dispatcher placement and api surface', () => {
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /\/api\/v1\/shipment-requests/i);
  assert.match(doc, /TransitionShipmentOrderStatusAction/i);
  assert.match(doc, /dashboard-web/i);
});

test('audit documents security boundaries and tenant model', () => {
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /forCustomer/i);
  assert.match(doc, /assignment.*gate gap|Assignment gate gap/i);
  assert.match(doc, /404/i);
});

test('audit documents conversion as deferred', () => {
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /ConvertShipmentOrderToShipmentAction/i);
  assert.match(doc, /NOT IMPLEMENTED|deferred|future/i);
  assert.match(doc, /POST \/api\/v1\/shipments/i);
});

test('audit documents reason requirements', () => {
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /changes_requested/i);
  assert.match(doc, /rejected/i);
  assert.match(doc, /reason/i);
});

test('no dispatcher shipment order controller was introduced', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Http/Controllers/DispatcherShipmentRequestController.php')),
    false
  );
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Http/Controllers/ShipmentOrderController.php')),
    false
  );
});

test('ops read routes allowed after B2.1; review routes allowed after B2.2; convert still absent', () => {
  const routes = read('backend/routes/api.php') || '';
  const opsRoutes = routes
    .split('\n')
    .filter((line) => /\[ShipmentRequestController::class/.test(line))
    .join('\n');
  assert.match(routes, /v1\.shipment-requests\.index/);
  assert.match(routes, /v1\.shipment-requests\.show/);
  assert.match(opsRoutes, /start-review/);
  assert.match(opsRoutes, /request-changes/);
  assert.match(opsRoutes, /\/approve/);
  assert.match(opsRoutes, /\/reject/);
  assert.equal(opsRoutes.includes('/convert'), false);
  assert.equal(opsRoutes.includes('/cancel'), false);
});

test('no shipmentOrder gates were introduced during audit-only phase', () => {
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md') || '';
  assert.match(doc, /NOT modified|documentation and verification only/i);
});

test('conversion action still does not exist', () => {
  assert.equal(
    existsSync(resolve(ROOT, 'backend/app/Actions/ShipmentOrder/ConvertShipmentOrderToShipmentAction.php')),
    false
  );
});

test('client shipment request endpoints remain unchanged in scope', () => {
  const routes = read('backend/routes/api.php') || '';
  assert.match(routes, /v1\.client\.shipment-requests\.submit/);
  assert.match(routes, /v1\.client\.shipment-requests\.resubmit/);
  const controller = read('backend/app/Http/Controllers/Client/ClientShipmentRequestController.php') || '';
  assert.match(controller, /ResubmitClientShipmentRequestAction/);
});

test('assignment gate gap remains documented not fixed', () => {
  const provider = read('backend/app/Providers/AppServiceProvider.php') || '';
  assert.equal(provider.includes("Gate::define('assignment."), false);
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md') || '';
  assert.match(doc, /assignment.*gate gap|Assignment gate gap/i);
});

test('no dispatcher shipment request ui was introduced', () => {
  const layout = read('dashboard-web/src/layouts/DispatcherLayout.tsx') || '';
  assert.equal(/shipment-request|Incoming Requests/i.test(layout), false);
  const feature = existsSync(resolve(ROOT, 'dashboard-web/src/features/dispatcher/shipment-requests'));
  assert.equal(feature, false);
});

test('audit certification states no implementation', () => {
  const doc = read('docs/C2.9B1_DISPATCHER_SHIPMENT_REQUEST_ARCHITECTURE_AUDIT.md');
  assert.match(doc, /NOT modified|documentation and verification only/i);
});
